<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Actions;

use Brain\Monkey\Functions;
use Cdek\Actions\CalculateDeliveryAction;
use Cdek\CdekApi;
use Cdek\MetaKeys;
use Cdek\Model\Tariff;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use ReflectionMethod;
use ReflectionProperty;

final class CalculateDeliveryActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('esc_html__')->returnArg();
        Functions\when('wc_get_logger')->justReturn(null);
        Functions\when('absint')->alias(static fn($value = 0) => abs((int)$value));
        Functions\when('get_option')->alias(static function (string $key) {
            return [
                'woocommerce_dimension_unit' => 'cm',
                'woocommerce_weight_unit'    => 'kg',
            ][$key] ?? false;
        });
    }

    /**
     * @return mixed
     */
    private function invokePrivate(object $object, string $method, array $args = [])
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    /**
     * @param  mixed  $value
     */
    private function setPrivateProperty(object $object, string $property, $value): void
    {
        $reflection = new ReflectionProperty($object, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }

    /**
     * `getPackagesData()` читает `$this->method` напрямую, а через `WeightConverter`
     * ещё раз вызывает *статический* `ShippingMethod::factory()` - оба пути должны
     * возвращать один и тот же настроенный мок, поэтому `factory()` замокан на $self.
     *
     * @return array{0: CalculateDeliveryAction, 1: MockInterface}
     */
    private function buildActionForPackages(): array
    {
        $shippingMethod = Mockery::mock('alias:' . ShippingMethod::class);
        $shippingMethod->shouldReceive('factory')->andReturn($shippingMethod);
        $shippingMethod->product_package_default_toggle = false;
        $shippingMethod->product_weight_default          = '0';

        $action = new CalculateDeliveryAction();
        $this->setPrivateProperty($action, 'method', $shippingMethod);

        return [$action, $shippingMethod];
    }

    private function stubDefaultDimensions(MockInterface $shippingMethod, int $length, int $width, int $height): void
    {
        $shippingMethod->shouldReceive('get_option')->with('product_length_default')->andReturn((string)$length);
        $shippingMethod->shouldReceive('get_option')->with('product_width_default')->andReturn((string)$width);
        $shippingMethod->shouldReceive('get_option')->with('product_height_default')->andReturn((string)$height);
    }

    private function mockProduct(string $weight, string $length, string $width, string $height): MockInterface
    {
        $product = Mockery::mock();
        $product->shouldReceive('get_weight')->andReturn($weight);
        $product->shouldReceive('get_length')->andReturn($length);
        $product->shouldReceive('get_width')->andReturn($width);
        $product->shouldReceive('get_height')->andReturn($height);

        return $product;
    }

    public function testGetPackagesDataConvertsMillimeterDimensionsToCentimeters(): void
    {
        [$action, $shippingMethod] = $this->buildActionForPackages();
        $this->stubDefaultDimensions($shippingMethod, 1, 1, 1);

        Functions\when('get_option')->alias(static function (string $key) {
            return [
                'woocommerce_dimension_unit' => 'mm',
                'woocommerce_weight_unit'    => 'kg',
            ][$key] ?? false;
        });

        $contents = [
            [
                'quantity' => 1,
                'data'     => $this->mockProduct('1.5', '1000', '500', '300'),
            ],
        ];

        $packages = $this->invokePrivate($action, 'getPackagesData', [$contents]);

        // мм/10 => [100, 50, 30], сортировка по возрастанию => length=30, height=50, width=100
        self::assertSame(30, $packages['length']);
        self::assertSame(100, $packages['width']);
        self::assertSame(50, $packages['height']);
        self::assertSame(1500, $packages['weight']);
    }

    public function testGetPackagesDataMultipliesSmallestDimensionByQuantity(): void
    {
        [$action, $shippingMethod] = $this->buildActionForPackages();
        $this->stubDefaultDimensions($shippingMethod, 1, 1, 1);

        $contents = [
            [
                'quantity' => 3,
                'data'     => $this->mockProduct('2', '10', '20', '5'),
            ],
        ];

        $packages = $this->invokePrivate($action, 'getPackagesData', [$contents]);

        // по возрастанию [5, 10, 20]; наименьшее (5) * qty(3) = 15 => пересортировка [10, 15, 20]
        self::assertSame(10, $packages['length']);
        self::assertSame(20, $packages['width']);
        self::assertSame(15, $packages['height']);
        self::assertSame(6000, $packages['weight']);
    }

    public function testGetPackagesDataPicksMaximumDimensionsAcrossProductsPerAxis(): void
    {
        [$action, $shippingMethod] = $this->buildActionForPackages();
        $this->stubDefaultDimensions($shippingMethod, 1, 1, 1);

        $contents = [
            [
                'quantity' => 1,
                'data'     => $this->mockProduct('1', '6', '4', '2'),
            ],
            [
                'quantity' => 1,
                'data'     => $this->mockProduct('2', '1', '10', '3'),
            ],
        ];

        $packages = $this->invokePrivate($action, 'getPackagesData', [$contents]);

        // товар A отсортирован [2,4,6] -> length=2 height=4 width=6
        // товар B отсортирован [1,3,10] -> length=1 height=3 width=10
        // максимум по каждой оси отдельно: length=max(2,1)=2, height=max(4,3)=4, width=max(6,10)=10
        self::assertSame(2, $packages['length']);
        self::assertSame(10, $packages['width']);
        self::assertSame(4, $packages['height']);
        self::assertSame(3000, $packages['weight']);
    }

    public function testGetPackagesDataForcesDefaultDimensionsWhenToggleEnabled(): void
    {
        [$action, $shippingMethod] = $this->buildActionForPackages();
        $shippingMethod->product_package_default_toggle = true;
        $this->stubDefaultDimensions($shippingMethod, 15, 25, 35);

        $contents = [
            [
                'quantity' => 1,
                // крупные размеры товара, которые иначе доминировали бы в результате
                'data'     => $this->mockProduct('5', '100', '80', '60'),
            ],
        ];

        $packages = $this->invokePrivate($action, 'getPackagesData', [$contents]);

        self::assertSame(15, $packages['length']);
        self::assertSame(25, $packages['width']);
        self::assertSame(35, $packages['height']);
    }

    public function testGetPackagesDataAppliesWeightFallbackWhenProductWeightIsEmpty(): void
    {
        [$action, $shippingMethod] = $this->buildActionForPackages();
        $shippingMethod->product_weight_default = '2,5';
        $this->stubDefaultDimensions($shippingMethod, 1, 1, 1);

        $contents = [
            [
                'quantity' => 2,
                'data'     => $this->mockProduct('', '10', '10', '10'),
            ],
        ];

        $packages = $this->invokePrivate($action, 'getPackagesData', [$contents]);

        // дефолтный вес 2.5кг (запятая конвертирована в точку) * qty 2 = 5кг -> 5000г
        self::assertSame(5000, $packages['weight']);
    }

    public function testGetPackagesDataConvertsWeightAccordingToConfiguredMeasurementUnit(): void
    {
        [$action, $shippingMethod] = $this->buildActionForPackages();
        $this->stubDefaultDimensions($shippingMethod, 1, 1, 1);

        Functions\when('get_option')->alias(static function (string $key) {
            return [
                'woocommerce_dimension_unit' => 'cm',
                'woocommerce_weight_unit'    => 'g',
            ][$key] ?? false;
        });

        $contents = [
            [
                'quantity' => 1,
                'data'     => $this->mockProduct('750', '10', '10', '10'),
            ],
        ];

        $packages = $this->invokePrivate($action, 'getPackagesData', [$contents]);

        self::assertSame(750, $packages['weight']);
    }

    /**
     * @return array{0: CalculateDeliveryAction, 1: MockInterface, 2: MockInterface}
     */
    private function buildActionWithMocks(?string $authError = null): array
    {
        $shippingMethod = Mockery::mock('alias:' . ShippingMethod::class);
        $shippingMethod->shouldReceive('factory')->andReturn($shippingMethod);
        $shippingMethod->city_code                          = '44';
        $shippingMethod->insurance                           = false;
        $shippingMethod->delivery_price_rules                = '';
        $shippingMethod->tariff_list                          = ['139', '138', '748'];
        $shippingMethod->extra_day                            = '0';
        $shippingMethod->tariff_name                          = '';
        $shippingMethod->product_package_default_toggle      = false;
        $shippingMethod->product_weight_default               = '0';
        $shippingMethod->services_ban_attachment_inspection  = false;
        $shippingMethod->services_trying_on                   = false;
        $shippingMethod->services_part_deliv                  = false;
        $shippingMethod->shouldReceive('get_option')
            ->with(Mockery::pattern('/^product_.+_default$/'))
            ->andReturn('10');

        $cdekApi = Mockery::mock('overload:' . CdekApi::class);
        $cdekApi->shouldReceive('authGetError')->andReturn($authError);

        $action = new CalculateDeliveryAction();

        return [$action, $cdekApi, $shippingMethod];
    }

    private function basePackage(array $overrides = []): array
    {
        return array_replace_recursive(
            [
                'destination'   => [
                    'postcode' => '101000',
                    'city'     => 'Moscow',
                    'country'  => 'RU',
                ],
                'contents_cost' => 1000,
                'contents'      => [],
            ],
            $overrides,
        );
    }

    private function tariffCode(
        int $code,
        int $periodMin = 1,
        int $periodMax = 1,
        int $deliverySum = 300,
        int $deliveryMode = 1
    ): array {
        return [
            'tariff_code'   => $code,
            'tariff_name'   => "Tariff $code",
            'period_min'    => $periodMin,
            'period_max'    => $periodMax,
            'delivery_sum'  => $deliverySum,
            'delivery_mode' => $deliveryMode,
        ];
    }

    public function testInvokeReturnsEmptyArrayWhenCityCodeIsEmpty(): void
    {
        [$action, , $shippingMethod] = $this->buildActionWithMocks();
        $shippingMethod->city_code = '';

        $result = $action($this->basePackage(), $shippingMethod);

        self::assertSame([], $result);
    }

    public function testInvokeReturnsEmptyArrayWhenAuthFails(): void
    {
        [$action, , $shippingMethod] = $this->buildActionWithMocks('invalid_client');

        $result = $action($this->basePackage(), $shippingMethod);

        self::assertSame([], $result);
    }

    /**
     * @dataProvider deliveryPeriodLabelProvider
     */
    public function testInvokeLabelReflectsDeliveryPeriod(
        int $periodMin,
        int $periodMax,
        string $expectedFragment
    ): void {
        [$action, $cdekApi, $shippingMethod] = $this->buildActionWithMocks();
        $shippingMethod->tariff_list = ['139'];

        $cdekApi->shouldReceive('calculateList')->andReturnUsing(
            function (array $param) use ($periodMin, $periodMax) {
                if ($param['type'] !== Tariff::SHOP_TYPE) {
                    return [];
                }

                return ['tariff_codes' => [$this->tariffCode(139, $periodMin, $periodMax)]];
            },
        );
        $cdekApi->shouldReceive('calculateGet')->andReturn(null);

        $rates = $action($this->basePackage(), $shippingMethod);

        self::assertCount(1, $rates);
        self::assertStringContainsString($expectedFragment, $rates[139]['label']);
    }

    public static function deliveryPeriodLabelProvider(): array
    {
        return [
            'single day when min equals max period' => [3, 3, '(3 day)'],
            'range when min differs from max period' => [2, 5, '(2-5 days)'],
        ];
    }

    public function testInvokeSkipsDuplicateTariffCodeAcrossShopAndDeliveryTypes(): void
    {
        [$action, $cdekApi, $shippingMethod] = $this->buildActionWithMocks();
        $shippingMethod->tariff_list = ['139'];

        // Один и тот же tariff_code возвращается и для SHOP_TYPE, и для DELIVERY_TYPE запроса.
        $cdekApi->shouldReceive('calculateList')->andReturn(['tariff_codes' => [$this->tariffCode(139)]]);
        $cdekApi->shouldReceive('calculateGet')->andReturn(null);

        $rates = $action($this->basePackage(), $shippingMethod);

        self::assertCount(1, $rates);
    }

    public function testInvokeFiltersOutTariffsNotInMethodTariffList(): void
    {
        [$action, $cdekApi, $shippingMethod] = $this->buildActionWithMocks();
        $shippingMethod->tariff_list = ['139'];

        $cdekApi->shouldReceive('calculateList')->andReturnUsing(function (array $param) {
            if ($param['type'] !== Tariff::SHOP_TYPE) {
                return [];
            }

            return ['tariff_codes' => [$this->tariffCode(139), $this->tariffCode(138)]];
        });
        $cdekApi->shouldReceive('calculateGet')->andReturn(null);

        $rates = $action($this->basePackage(), $shippingMethod);

        self::assertCount(1, $rates);
        self::assertSame(139, $rates[139]['meta_data'][MetaKeys::TARIFF_CODE]);
    }

    public function testInvokeExcludesToOfficeTariffsWhenAddTariffsToOfficeIsFalse(): void
    {
        [$action, $cdekApi, $shippingMethod] = $this->buildActionWithMocks();
        // 138 = "посылка дверь-склад", Tariff::isToOffice(138) === true
        $shippingMethod->tariff_list = ['138'];

        $cdekApi->shouldReceive('calculateList')->andReturnUsing(function (array $param) {
            if ($param['type'] !== Tariff::SHOP_TYPE) {
                return [];
            }

            return ['tariff_codes' => [$this->tariffCode(138, 1, 1, 200, 3)]];
        });
        $cdekApi->shouldReceive('calculateGet')->andReturn(null);

        $rates = $action($this->basePackage(), $shippingMethod, false);

        self::assertSame([], $rates);
    }

    /**
     * @dataProvider costComputationProvider
     */
    public function testInvokeComputesFinalCost(array $rule, ?float $calculateGetResult, $expectedCost): void
    {
        [$action, $cdekApi, $shippingMethod] = $this->buildActionWithMocks();
        $shippingMethod->tariff_list          = ['139'];
        $shippingMethod->delivery_price_rules = json_encode([
            'office' => [['type' => 'percentage', 'to' => null, 'value' => 100]],
            'door'   => [$rule],
        ]);

        $cdekApi->shouldReceive('calculateList')->andReturnUsing(function (array $param) {
            if ($param['type'] !== Tariff::SHOP_TYPE) {
                return [];
            }

            return ['tariff_codes' => [$this->tariffCode(139, 1, 1, 300)]];
        });
        $cdekApi->shouldReceive('calculateGet')->andReturn($calculateGetResult);

        $rates = $action($this->basePackage(), $shippingMethod);

        self::assertSame($expectedCost, $rates[139]['cost']);
    }

    public static function costComputationProvider(): array
    {
        return [
            'free rule zeroes cost regardless of calculated price' => [
                ['type' => 'free', 'to' => null, 'value' => 0], 500.0, 0,
            ],
            'fixed rule overrides cost with the configured value' => [
                ['type' => 'fixed', 'to' => null, 'value' => 250], 500.0, 250,
            ],
            'amount rule adds a flat value on top of the calculated price' => [
                ['type' => 'amount', 'to' => null, 'value' => 50], 200.0, 250.0,
            ],
            'percentage rule scales the calculated price' => [
                ['type' => 'percentage', 'to' => null, 'value' => 150], 200.0, 300.0,
            ],
            'falls back to tariff list cost when live calculation is unavailable' => [
                ['type' => 'percentage', 'to' => null, 'value' => 100], null, 300.0,
            ],
        ];
    }
}
