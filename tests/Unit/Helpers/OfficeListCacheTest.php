<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Helpers;

use Brain\Monkey\Functions;
use Cdek\Helpers\OfficeListCache;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;

final class OfficeListCacheTest extends TestCase
{
    private const CITY     = 'Новосибирск';
    private const POSTCODE = '630009';

    private function mockSession(?object $session): void
    {
        $wc          = Mockery::mock();
        $wc->session = $session;

        Functions\when('WC')->justReturn($wc);
    }

    /**
     * Ключ кэша - приватная деталь реализации (md5 от city+postcode). Вместо
     * дублирования формулы в тесте перехватываем реальный ключ через аргумент
     * вызова `set()` на том же мок-объекте сессии.
     */
    private function captureKeyFromSet(MockInterface $session, string $city, ?string $postcode, array $data): string
    {
        $captured = null;

        $session->shouldReceive('set')
                ->once()
                ->with('official_cdek_office_list_cache', Mockery::on(static function (array $value) use (&$captured) {
                    $captured = $value;

                    return true;
                }));

        OfficeListCache::set($city, $postcode, $data);

        return $captured['key'];
    }

    public function testGetReturnsNullWhenSessionIsNull(): void
    {
        $this->mockSession(null);

        self::assertNull(OfficeListCache::get(self::CITY, self::POSTCODE));
    }

    public function testGetReturnsNullWhenCacheIsEmpty(): void
    {
        $session = Mockery::mock();
        $this->mockSession($session);

        $session->shouldReceive('get')->with('official_cdek_office_list_cache')->andReturn(null);

        self::assertNull(OfficeListCache::get(self::CITY, self::POSTCODE));
    }

    public function testGetReturnsNullWhenStoredKeyDoesNotMatch(): void
    {
        $session = Mockery::mock();
        $this->mockSession($session);

        $session->shouldReceive('get')->with('official_cdek_office_list_cache')->andReturn([
            'key'     => 'not-a-real-key',
            'expires' => time() + 100,
            'data'    => ['city' => '270', 'points' => '[]'],
        ]);

        self::assertNull(OfficeListCache::get(self::CITY, self::POSTCODE));
    }

    public function testGetReturnsNullWhenExpired(): void
    {
        $session = Mockery::mock();
        $this->mockSession($session);

        $data = ['city' => '270', 'points' => '[]'];
        $key  = $this->captureKeyFromSet($session, self::CITY, self::POSTCODE, $data);

        $session->shouldReceive('get')->with('official_cdek_office_list_cache')->andReturn([
            'key'     => $key,
            'expires' => time() - 1,
            'data'    => $data,
        ]);

        self::assertNull(OfficeListCache::get(self::CITY, self::POSTCODE));
    }

    public function testGetReturnsCachedDataOnHit(): void
    {
        $session = Mockery::mock();
        $this->mockSession($session);

        $data = ['city' => '270', 'points' => '[{"code":"NSK1"}]'];
        $key  = $this->captureKeyFromSet($session, self::CITY, self::POSTCODE, $data);

        $session->shouldReceive('get')->with('official_cdek_office_list_cache')->andReturn([
            'key'     => $key,
            'expires' => time() + 100,
            'data'    => $data,
        ]);

        self::assertSame($data, OfficeListCache::get(self::CITY, self::POSTCODE));
    }

    public function testCacheKeyDiffersForDifferentCities(): void
    {
        $session = Mockery::mock();
        $this->mockSession($session);

        $data   = ['city' => '270', 'points' => '[]'];
        $keyOne = $this->captureKeyFromSet($session, self::CITY, self::POSTCODE, $data);
        $keyTwo = $this->captureKeyFromSet($session, 'Москва', self::POSTCODE, $data);

        self::assertNotSame($keyOne, $keyTwo);
    }

    public function testCacheKeyDiffersForDifferentPostcodes(): void
    {
        $session = Mockery::mock();
        $this->mockSession($session);

        $data   = ['city' => '270', 'points' => '[]'];
        $keyOne = $this->captureKeyFromSet($session, self::CITY, self::POSTCODE, $data);
        $keyTwo = $this->captureKeyFromSet($session, self::CITY, '101000', $data);

        self::assertNotSame($keyOne, $keyTwo);
    }

    public function testCacheKeyHandlesNullPostcode(): void
    {
        $session = Mockery::mock();
        $this->mockSession($session);

        $data = ['city' => '270', 'points' => '[]'];

        // Не должно бросать TypeError на null postcode.
        $key = $this->captureKeyFromSet($session, self::CITY, null, $data);

        self::assertIsString($key);
    }

    public function testSetDoesNothingWhenSessionIsNull(): void
    {
        $this->mockSession(null);

        // Просто не должно бросить исключение при отсутствии сессии.
        OfficeListCache::set(self::CITY, self::POSTCODE, ['city' => '270', 'points' => '[]']);
        self::assertTrue(true);
    }

    public function testSetStoresDataWithFutureExpiry(): void
    {
        $session = Mockery::mock();
        $this->mockSession($session);

        $data     = ['city' => '270', 'points' => '[]'];
        $captured = null;

        $session->shouldReceive('set')
                ->once()
                ->with('official_cdek_office_list_cache', Mockery::on(static function (array $value) use (&$captured) {
                    $captured = $value;

                    return true;
                }));

        OfficeListCache::set(self::CITY, self::POSTCODE, $data);

        self::assertSame($data, $captured['data']);
        self::assertGreaterThan(time(), $captured['expires']);
    }
}
