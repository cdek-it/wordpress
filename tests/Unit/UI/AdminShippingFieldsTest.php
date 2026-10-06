<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\UI;

use Brain\Monkey\Functions;
use Cdek\CdekApi;
use Cdek\Exceptions\External\ApiException;
use Cdek\Loader;
use Cdek\MetaKeys;
use Cdek\Tests\TestCase;
use Cdek\UI\AdminShippingFields;
use Mockery;
use Mockery\MockInterface;
use ReflectionProperty;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AdminShippingFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $property = new ReflectionProperty(Loader::class, 'pluginName');
        $property->setAccessible(true);
        $property->setValue(null, 'CDEK');

        Functions\when('esc_html__')->returnArg();
        Functions\when('esc_html')->alias(static fn($value) => htmlspecialchars((string)$value));
        Functions\when('get_option')->alias(
            static fn(string $name) => $name === 'woocommerce_weight_unit' ? 'kg' : false,
        );
    }

    /**
     * @param array<int, array{0: string, 1: mixed}> $meta пары [ключ, значение] меты позиции доставки
     */
    private function shippingItem(array $meta, string $methodId = 'official_cdek'): MockInterface
    {
        $entries = array_map(
            static function (array $pair): MockInterface {
                $entry = Mockery::mock('WC_Meta_Data');
                $entry->shouldReceive('get_data')->andReturn(['key' => $pair[0], 'value' => $pair[1]]);

                return $entry;
            },
            $meta,
        );

        $item = Mockery::mock('WC_Order_Item_Shipping');
        $item->shouldReceive('get_method_id')->andReturn($methodId);
        $item->shouldReceive('get_meta_data')->andReturn($entries);

        return $item;
    }

    private function render($item): string
    {
        ob_start();

        try {
            (new AdminShippingFields())(1, $item);
        } finally {
            $output = (string)ob_get_clean();
        }

        return $output;
    }

    // ---- какие позиции обрабатываются ----

    public function testIgnoresItemsThatAreNotShippingItems(): void
    {
        self::assertSame('', $this->render(new \stdClass()));
        self::assertSame('', $this->render(Mockery::mock('WC_Order_Item_Product')));
    }

    public function testIgnoresShippingItemsOfOtherMethods(): void
    {
        $item = $this->shippingItem([[MetaKeys::LENGTH, '10']], 'flat_rate');

        self::assertSame('', $this->render($item));
    }

    public function testRendersNothingForItemWithoutMeta(): void
    {
        self::assertSame('', $this->render($this->shippingItem([])));
    }

    public function testIgnoresUnknownMetaKeys(): void
    {
        $item = $this->shippingItem([['_some_other_meta', 'value'], [MetaKeys::TARIFF_MODE, '4']]);

        self::assertSame('', $this->render($item));
    }

    // ---- вес ----

    /**
     * @dataProvider legacyWeightKeyProvider
     */
    public function testLegacyWeightKeysAreShownAsStoredWithCurrentUnit(string $key): void
    {
        self::assertSame(
            '<div>Weight: 2.5kg</div>',
            $this->render($this->shippingItem([[$key, '2.5']])),
        );
    }

    public static function legacyWeightKeyProvider(): array
    {
        return [
            'grams'  => ['weight (g)'],
            'ounces' => ['weight (oz)'],
            'pounds' => ['weight (lbs)'],
            'kg'     => ['weight (kg)'],
        ];
    }

    /**
     * @dataProvider gramWeightKeyProvider
     */
    public function testWeightInGramsIsConvertedToStoreUnit(string $key): void
    {
        self::assertSame(
            '<div>Weight: 1.5kg</div>',
            $this->render($this->shippingItem([[$key, 1500]])),
        );
    }

    public static function gramWeightKeyProvider(): array
    {
        return [
            'legacy key'  => ['weight'],
            'current key' => [MetaKeys::WEIGHT],
        ];
    }

    public function testWeightFallsBackToGramsForUnsupportedStoreUnit(): void
    {
        Functions\when('get_option')->justReturn('stone');

        self::assertSame(
            '<div>Weight: 1500stone</div>',
            $this->render($this->shippingItem([[MetaKeys::WEIGHT, 1500]])),
        );
    }

    // ---- габариты ----

    /**
     * @dataProvider dimensionProvider
     */
    public function testDimensionsAreRenderedUnderBothLegacyAndCurrentKeys(string $key, string $expected): void
    {
        self::assertSame($expected, $this->render($this->shippingItem([[$key, '25']])));
    }

    public static function dimensionProvider(): array
    {
        return [
            'current length' => [MetaKeys::LENGTH, '<div>Length: 25</div>'],
            'legacy length'  => ['length', '<div>Length: 25</div>'],
            'current width'  => [MetaKeys::WIDTH, '<div>Width: 25</div>'],
            'legacy width'   => ['width', '<div>Width: 25</div>'],
            'current height' => [MetaKeys::HEIGHT, '<div>Height: 25</div>'],
            'legacy height'  => ['height', '<div>Height: 25</div>'],
        ];
    }

    // ---- тариф ----

    /**
     * @dataProvider tariffKeyProvider
     */
    public function testTariffCodeIsRenderedUnderBothLegacyAndCurrentKeys(string $key): void
    {
        self::assertSame(
            '<div>Tariff code: 139</div>',
            $this->render($this->shippingItem([[$key, '139']])),
        );
    }

    public static function tariffKeyProvider(): array
    {
        return [
            'legacy key'  => ['tariff_code'],
            'current key' => [MetaKeys::TARIFF_CODE],
        ];
    }

    // ---- ПВЗ ----

    public function testLegacyPvzKeyIsShownAsStored(): void
    {
        self::assertSame(
            '<div>Selected pickup point: NSK17</div>',
            $this->render($this->shippingItem([['pvz', 'NSK17']])),
        );
    }

    public function testOfficeCodeIsShownWithAddressFromApi(): void
    {
        $api = Mockery::mock('overload:' . CdekApi::class);
        $api->shouldReceive('officeGet')->with('NSK17')->andReturn(
            ['location' => ['address' => 'ул. Ленина, 1']],
        );

        self::assertSame(
            '<div>Selected pickup point: NSK17 (ул. Ленина, 1)</div>',
            $this->render($this->shippingItem([[MetaKeys::OFFICE_CODE, 'NSK17']])),
        );
    }

    public function testOfficeCodeIsReportedUnavailableWhenApiDoesNotKnowIt(): void
    {
        $api = Mockery::mock('overload:' . CdekApi::class);
        $api->shouldReceive('officeGet')->andReturn(null);

        set_error_handler(static fn(): bool => true, E_WARNING);

        try {
            $output = $this->render($this->shippingItem([[MetaKeys::OFFICE_CODE, 'GONE1']]));
        } finally {
            restore_error_handler();
        }

        self::assertSame('<div>Selected pickup point: Not available for order</div>', $output);
    }

    public function testOfficeCodeIsReportedUnavailableWhenApiFails(): void
    {
        $api = Mockery::mock('overload:' . CdekApi::class);
        $api->shouldReceive('officeGet')->andThrow(new ApiException([]));

        self::assertSame(
            '<div>Selected pickup point: Not available for order</div>',
            $this->render($this->shippingItem([[MetaKeys::OFFICE_CODE, 'NSK17']])),
        );
    }

    // ---- общее ----

    public function testRendersEveryKnownFieldInStoredOrder(): void
    {
        $api = Mockery::mock('overload:' . CdekApi::class);
        $api->shouldReceive('officeGet')->andReturn(['location' => ['address' => 'Адрес']]);

        $item = $this->shippingItem([
            [MetaKeys::TARIFF_CODE, '136'],
            [MetaKeys::TARIFF_MODE, '4'],
            [MetaKeys::OFFICE_CODE, 'NSK17'],
            [MetaKeys::WEIGHT, 2000],
            [MetaKeys::LENGTH, '10'],
            [MetaKeys::WIDTH, '20'],
            [MetaKeys::HEIGHT, '30'],
        ]);

        self::assertSame(
            '<div>Tariff code: 136</div>'
            . '<div>Selected pickup point: NSK17 (Адрес)</div>'
            . '<div>Weight: 2kg</div>'
            . '<div>Length: 10</div>'
            . '<div>Width: 20</div>'
            . '<div>Height: 30</div>',
            $this->render($item),
        );
    }

    public function testValuesAreEscapedBeforeOutput(): void
    {
        self::assertSame(
            '<div>Tariff code: &lt;script&gt;alert(1)&lt;/script&gt;</div>',
            $this->render($this->shippingItem([[MetaKeys::TARIFF_CODE, '<script>alert(1)</script>']])),
        );
    }
}
