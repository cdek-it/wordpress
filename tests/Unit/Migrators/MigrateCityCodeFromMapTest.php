<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Migrators;

use Brain\Monkey\Functions;
use Cdek\CdekApi;
use Cdek\Migrators\MigrateCityCodeFromMap;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Exception;
use Mockery;
use Mockery\MockInterface;

final class MigrateCityCodeFromMapTest extends TestCase
{
    private const OFFICE_JSON  = '{"city":"Москва","postal":"101000","country":"RU"}';
    private const ADDRESS_JSON = '{"city":"Новосибирск","postal":"630000","country":"RU","address":"ул. Ленина, 1"}';

    /** @var array<string, mixed> */
    private array $updatedOptions = [];

    private MockInterface $api;

    protected function setUp(): void
    {
        parent::setUp();

        $this->updatedOptions = [];

        Functions\when('wc_get_logger')->justReturn(null);

        $this->api = Mockery::mock('overload:' . CdekApi::class);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function mockShippingMethod(array $options): MockInterface
    {
        $method = Mockery::mock('alias:' . ShippingMethod::class);
        $method->shouldReceive('get_option')->andReturnUsing(
            fn(string $key) => $this->updatedOptions[$key] ?? $options[$key] ?? null,
        );
        $method->shouldReceive('update_option')->andReturnUsing(
            function (string $key, $value): bool {
                $this->updatedOptions[$key] = $value;

                return true;
            },
        );

        return $method;
    }

    private function migrate(array $options): void
    {
        (new MigrateCityCodeFromMap())($this->mockShippingMethod($options));
    }

    public function testDoesNothingWhenNoLegacyDataIsStored(): void
    {
        $this->api->shouldReceive('cityGet')->never();

        $this->migrate([]);

        self::assertSame([], $this->updatedOptions);
    }

    public function testUsesDefaultShippingMethodWhenNoneIsPassed(): void
    {
        $method = $this->mockShippingMethod([]);
        $method->shouldReceive('factory')->once()->andReturn($method);

        (new MigrateCityCodeFromMap())();

        self::assertSame([], $this->updatedOptions);
    }

    // ---- legacy office (pvz_code) ----

    public function testExchangesCityCodeFromLegacyOffice(): void
    {
        $this->api->shouldReceive('cityGet')
                  ->once()
                  ->with('Москва', '101000', 'RU')
                  ->andReturn(['code' => 44, 'city' => 'Москва']);

        $this->migrate(['pvz_code' => self::OFFICE_JSON]);

        self::assertSame(['city_code' => 44, 'city' => 'Москва'], $this->updatedOptions);
    }

    public function testDoesNotOverwriteAlreadyConfiguredCityCode(): void
    {
        $this->api->shouldReceive('cityGet')->never();

        $this->migrate(['pvz_code' => self::OFFICE_JSON, 'city_code' => '44']);

        self::assertSame([], $this->updatedOptions);
    }

    /**
     * @dataProvider unusableLegacyDataProvider
     */
    public function testSkipsUnusableLegacyOffice(string $legacyOffice): void
    {
        $this->api->shouldReceive('cityGet')->never();

        $this->migrate(['pvz_code' => $legacyOffice]);

        self::assertSame([], $this->updatedOptions);
    }

    public static function unusableLegacyDataProvider(): array
    {
        return [
            'invalid json'    => ['not-a-json'],
            'no city'         => ['{"postal":"101000","country":"RU"}'],
            'no postal'       => ['{"city":"Москва","country":"RU"}'],
            'no country'      => ['{"city":"Москва","postal":"101000"}'],
            'empty city part' => ['{"city":"","postal":"101000","country":"RU"}'],
        ];
    }

    public function testLeavesOptionsUntouchedWhenCityLookupThrows(): void
    {
        $this->api->shouldReceive('cityGet')->once()->andThrow(new Exception('API is down'));

        $this->migrate(['pvz_code' => self::OFFICE_JSON]);

        self::assertSame([], $this->updatedOptions);
    }

    public function testLeavesOptionsUntouchedWhenCityIsNotFound(): void
    {
        $this->api->shouldReceive('cityGet')->once()->andReturn(null);

        $this->migrate(['pvz_code' => self::OFFICE_JSON]);

        self::assertSame([], $this->updatedOptions);
    }

    // ---- legacy address ----

    public function testMigratesLegacyAddressAndExchangesCityCode(): void
    {
        $this->api->shouldReceive('cityGet')
                  ->once()
                  ->with('Новосибирск', '630000', 'RU')
                  ->andReturn(['code' => 270, 'city' => 'Новосибирск']);

        $this->migrate(['address' => self::ADDRESS_JSON]);

        self::assertSame(
            [
                'legacy_address' => self::ADDRESS_JSON,
                'city_code'      => 270,
                'city'           => 'Новосибирск',
                'address'        => 'ул. Ленина, 1',
            ],
            $this->updatedOptions,
        );
    }

    public function testDoesNotExchangeCityCodeForAddressWhenCityCodeOptionIsNotNull(): void
    {
        $this->api->shouldReceive('cityGet')->never();

        $this->migrate(['address' => self::ADDRESS_JSON, 'city_code' => '']);

        self::assertSame(
            ['legacy_address' => self::ADDRESS_JSON, 'address' => 'ул. Ленина, 1'],
            $this->updatedOptions,
        );
    }

    public function testStillMigratesAddressWhenCityLookupFails(): void
    {
        $this->api->shouldReceive('cityGet')->once()->andThrow(new Exception('API is down'));

        $this->migrate(['address' => self::ADDRESS_JSON]);

        self::assertSame(
            ['legacy_address' => self::ADDRESS_JSON, 'address' => 'ул. Ленина, 1'],
            $this->updatedOptions,
        );
    }

    public function testSkipsAddressThatIsNotValidJson(): void
    {
        $this->api->shouldReceive('cityGet')->never();

        $this->migrate(['address' => 'ул. Ленина, 1']);

        self::assertSame([], $this->updatedOptions);
    }

    public function testKeepsLegacyCopyButSkipsAddressWithMissingParts(): void
    {
        $this->api->shouldReceive('cityGet')->never();

        $address = '{"city":"Новосибирск","address":"ул. Ленина, 1"}';

        $this->migrate(['address' => $address]);

        self::assertSame(['legacy_address' => $address], $this->updatedOptions);
    }

    public function testMigratesBothOfficeAndAddressInOneRunWithSingleCityLookup(): void
    {
        $calls = 0;
        $this->api->shouldReceive('cityGet')->andReturnUsing(
            function () use (&$calls): array {
                $calls++;

                return ['code' => 44, 'city' => 'Москва'];
            },
        );

        $this->migrate(['pvz_code' => self::OFFICE_JSON, 'address' => self::ADDRESS_JSON]);

        self::assertSame(1, $calls);
        self::assertSame(
            [
                'city_code'      => 44,
                'city'           => 'Москва',
                'legacy_address' => self::ADDRESS_JSON,
                'address'        => 'ул. Ленина, 1',
            ],
            $this->updatedOptions,
        );
    }
}
