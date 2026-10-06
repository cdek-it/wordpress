<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Blocks;

use Brain\Monkey\Functions;
use Cdek\Blocks\CheckoutMapBlock;
use Cdek\CdekApi;
use Cdek\Exceptions\External\ApiException;
use Cdek\Helpers\CheckoutHelper;
use Cdek\Helpers\OfficeListCache;
use Cdek\Helpers\UI;
use Cdek\Loader;
use Cdek\MetaKeys;
use Cdek\Model\Tariff;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use ReflectionProperty;
use RuntimeException;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class CheckoutMapBlockTest extends TestCase
{
    /** @var array<int, array> вызовы woocommerce_store_api_register_endpoint_data() */
    private array $registered = [];

    /** @var array<int, array> */
    private array $cacheWrites = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->registered  = [];
        $this->cacheWrites = [];

        $this->declareWooCommerceStubs();

        $property = new ReflectionProperty(Loader::class, 'pluginName');
        $property->setAccessible(true);
        $property->setValue(null, 'CDEK');

        Functions\when('esc_html__')->returnArg();

        if (!defined('ARRAY_A')) {
            define('ARRAY_A', 'ARRAY_A');
        }
    }

    private function declareWooCommerceStubs(): void
    {
        eval(<<<'PHP'
            namespace Automattic\WooCommerce\Blocks\Integrations {
                interface IntegrationInterface
                {
                    public function get_name();
                    public function initialize();
                    public function get_script_handles();
                    public function get_editor_script_handles();
                    public function get_script_data();
                }
            }

            namespace Automattic\WooCommerce\StoreApi\Schemas\V1 {
                class CartSchema { const IDENTIFIER = 'cart'; }
                class CheckoutSchema { const IDENTIFIER = 'checkout'; }
            }

            namespace {
                class WP_REST_Request implements ArrayAccess
                {
                    public array $params = [];

                    public function offsetExists(mixed $offset): bool
                    {
                        return isset($this->params[$offset]);
                    }

                    public function offsetGet(mixed $offset): mixed
                    {
                        return $this->params[$offset] ?? null;
                    }

                    public function offsetSet(mixed $offset, mixed $value): void
                    {
                        $this->params[$offset] = $value;
                    }

                    public function offsetUnset(mixed $offset): void
                    {
                        unset($this->params[$offset]);
                    }
                }
            }
            PHP);
    }

    private function helper(): MockInterface
    {
        return Mockery::mock('alias:' . CheckoutHelper::class);
    }

    /**
     * @param array<string, ?string> $values значения CheckoutHelper::getCurrentValue()
     */
    private function mockCheckout(array $values, ?object $rate = null, bool $suitable = true): void
    {
        $helper = $this->helper();
        $helper->shouldReceive('getCurrentValue')->andReturnUsing(
            static fn(string $name) => $values[$name] ?? null,
        );
        $helper->shouldReceive('getSelectedShippingRate')->andReturn($rate);
        $helper->shouldReceive('isShippingRateSuitable')->andReturn($suitable);
    }

    private function rate(array $meta): MockInterface
    {
        $rate = Mockery::mock();
        $rate->shouldReceive('get_meta_data')->andReturn($meta);

        return $rate;
    }

    /**
     * @param array<string, mixed>|null $cached содержимое кэша списка ПВЗ
     */
    private function mockOfficeCache(?array $cached): void
    {
        $cache = Mockery::mock('alias:' . OfficeListCache::class);
        $cache->shouldReceive('get')->andReturn($cached);
        $cache->shouldReceive('set')->andReturnUsing(
            function (string $city, ?string $postcode, array $data): void {
                $this->cacheWrites[] = [$city, $postcode, $data];
            },
        );
    }

    // ---- регистрация данных в Store API ----

    public function testAddStoreApiDataRegistersCartAndCheckoutExtensions(): void
    {
        Functions\when('woocommerce_store_api_register_endpoint_data')->alias(
            function (array $args): void {
                $this->registered[] = $args;
            },
        );

        CheckoutMapBlock::addStoreApiData();

        self::assertSame(
            [
                [
                    'endpoint'        => 'cart',
                    'namespace'       => 'official_cdek',
                    'schema_callback' => [CheckoutMapBlock::class, 'extend_cart_schema'],
                    'schema_type'     => ARRAY_A,
                    'data_callback'   => [CheckoutMapBlock::class, 'extend_cart_data'],
                ],
                [
                    'endpoint'        => 'checkout',
                    'namespace'       => 'official_cdek',
                    'schema_callback' => [CheckoutMapBlock::class, 'extend_checkout_schema'],
                    'schema_type'     => ARRAY_A,
                    'data_callback'   => [CheckoutMapBlock::class, 'extend_checkout_data'],
                ],
            ],
            $this->registered,
        );
    }

    // ---- extend_cart_data ----

    public function testCartDataWithoutCityReturnsEmptyPointsAndTariffMode(): void
    {
        $this->mockCheckout(['city' => ''], $this->rate([MetaKeys::TARIFF_MODE => '4']));

        self::assertSame(['points' => '[]', 'tariffMode' => 4], CheckoutMapBlock::extend_cart_data());
    }

    public function testCartDataUsesCachedOfficeList(): void
    {
        $this->mockCheckout(['city' => 'Москва', 'postcode' => '101000']);
        $this->mockOfficeCache(['city' => '44', 'points' => '[{"code":"MSK1"}]']);

        self::assertSame(
            [
                'inputs'     => ['city' => 'Москва', 'postcode' => '101000'],
                'city'       => '44',
                'points'     => '[{"code":"MSK1"}]',
                'tariffMode' => null,
            ],
            CheckoutMapBlock::extend_cart_data(),
        );
        self::assertSame([], $this->cacheWrites);
    }

    public function testCartDataFetchesOfficesAndCachesThemOnCacheMiss(): void
    {
        $this->mockCheckout(['city' => 'Москва', 'postcode' => '101000']);
        $this->mockOfficeCache(null);

        $api = Mockery::mock('overload:' . CdekApi::class);
        $api->shouldReceive('cityCodeGet')->with('Москва', '101000')->andReturn('44');
        $api->shouldReceive('officeListRaw')->with('44')->andReturn('[{"code":"MSK1"}]');

        $result = CheckoutMapBlock::extend_cart_data();

        self::assertSame('44', $result['city']);
        self::assertSame('[{"code":"MSK1"}]', $result['points']);
        self::assertSame(
            [['Москва', '101000', ['city' => '44', 'points' => '[{"code":"MSK1"}]']]],
            $this->cacheWrites,
        );
    }

    public function testCartDataDoesNotCacheUnresolvedCity(): void
    {
        $this->mockCheckout(['city' => 'Нигде', 'postcode' => '000000']);
        $this->mockOfficeCache(null);

        $api = Mockery::mock('overload:' . CdekApi::class);
        $api->shouldReceive('cityCodeGet')->andReturn(null);
        $api->shouldReceive('officeListRaw')->andReturn('must-not-be-requested');

        $result = CheckoutMapBlock::extend_cart_data();

        self::assertNull($result['city']);
        self::assertSame('[]', $result['points']);
        self::assertSame([], $this->cacheWrites);
    }

    public function testCartDataFallsBackToEmptyListAndSkipsCacheWhenApiFails(): void
    {
        $this->mockCheckout(['city' => 'Москва', 'postcode' => '101000']);
        $this->mockOfficeCache(null);

        $api = Mockery::mock('overload:' . CdekApi::class);
        $api->shouldReceive('cityCodeGet')->andThrow(new ApiException([]));

        $result = CheckoutMapBlock::extend_cart_data();

        self::assertNull($result['city']);
        self::assertSame('[]', $result['points']);
        self::assertSame([], $this->cacheWrites);
    }

    /**
     * @dataProvider tariffModeProvider
     */
    public function testCartDataReportsModeOfSelectedCdekRate(?array $meta, bool $suitable, ?int $expected): void
    {
        $this->mockCheckout(['city' => ''], $meta === null ? null : $this->rate($meta), $suitable);

        self::assertSame($expected, CheckoutMapBlock::extend_cart_data()['tariffMode']);
    }

    public static function tariffModeProvider(): array
    {
        return [
            'no selected rate'          => [null, true, null],
            'rate of another method'    => [[MetaKeys::TARIFF_MODE => '4'], false, null],
            'mode stored as string'     => [[MetaKeys::TARIFF_MODE => '4'], true, 4],
            'mode stored as int'        => [[MetaKeys::TARIFF_MODE => 1], true, 1],
            'rate without mode'         => [[], true, null],
        ];
    }

    // ---- схемы ----

    public function testCartSchemaDescribesPointsAndTariffMode(): void
    {
        $schema = CheckoutMapBlock::extend_cart_schema();

        self::assertSame(['points', 'tariffMode'], array_keys($schema));
        self::assertSame('string', $schema['points']['type']);
        self::assertSame(['integer', 'null'], $schema['tariffMode']['type']);

        foreach ($schema as $field) {
            self::assertTrue($field['readonly']);
            self::assertSame(['view', 'edit'], $field['context']);
        }
    }

    public function testCheckoutSchemaDescribesOfficeCode(): void
    {
        $schema = CheckoutMapBlock::extend_checkout_schema();

        self::assertSame(['office_code'], array_keys($schema));
        self::assertSame(['string', 'null'], $schema['office_code']['type']);
        self::assertTrue($schema['office_code']['readonly']);
        self::assertSame(['view', 'edit'], $schema['office_code']['context']);
    }

    // ---- extend_checkout_data ----

    private function mockSession(?object $session): void
    {
        $wc          = Mockery::mock();
        $wc->session = $session;

        Functions\when('WC')->justReturn($wc);
    }

    /**
     * @dataProvider sessionOfficeProvider
     */
    public function testCheckoutDataReportsOfficeFromSession($stored, ?string $expected): void
    {
        $session = Mockery::mock();
        $session->shouldReceive('get')->with('official_cdek_office_code')->andReturn($stored);
        $this->mockSession($session);

        self::assertSame(['office_code' => $expected], CheckoutMapBlock::extend_checkout_data());
    }

    public static function sessionOfficeProvider(): array
    {
        return [
            'selected office' => ['NSK17', 'NSK17'],
            'empty string'    => ['', null],
            'nothing stored'  => [null, null],
        ];
    }

    public function testCheckoutDataReturnsNullOfficeWhenSessionIsUnavailable(): void
    {
        $this->mockSession(null);

        self::assertSame(['office_code' => null], CheckoutMapBlock::extend_checkout_data());
    }

    // ---- saveOrderData ----

    /**
     * @return array{0: \WC_Order, 1: MockInterface} заказ и позиция доставки (для проверки сохранённой меты)
     */
    private function orderWithShipping(?int $tariff): array
    {
        $wcOrder = Mockery::mock('WC_Order');
        $wcOrder->shouldReceive('get_meta')->with('order_data')->andReturn([]);

        if ($tariff === null) {
            $wcOrder->shouldReceive('get_shipping_methods')->andReturn([]);

            return [$wcOrder, Mockery::mock()];
        }

        $meta = Mockery::mock('WC_Meta_Data');
        $meta->shouldReceive('get_data')->andReturn(['key' => MetaKeys::TARIFF_CODE, 'value' => (string)$tariff]);

        $item = Mockery::mock('WC_Order_Item_Shipping');
        $item->shouldReceive('get_method_id')->andReturn('official_cdek');
        $item->shouldReceive('get_data')->andReturn(['instance_id' => 0]);
        $item->shouldReceive('get_meta_data')->andReturn([$meta]);

        $wcOrder->shouldReceive('get_shipping_methods')->andReturn([$item]);

        return [$wcOrder, $item];
    }

    private function request(?string $officeCode): \WP_REST_Request
    {
        $request                = new \WP_REST_Request();
        $request['extensions']  = $officeCode === null ? [] : ['official_cdek' => ['office_code' => $officeCode]];

        return $request;
    }

    public function testSaveOrderDataDoesNothingWhenOrderHasNoCdekShipping(): void
    {
        [$order] = $this->orderWithShipping(null);

        $session = Mockery::mock();
        $session->shouldReceive('set')->never();
        $this->mockSession($session);

        CheckoutMapBlock::saveOrderData($order, $this->request('NSK17'));

        $this->addToAssertionCount(1);
    }

    public function testSaveOrderDataStoresOfficeForToOfficeTariffAndKeepsItInSession(): void
    {
        [$order, $item] = $this->orderWithShipping(136);

        $sessionWrites = [];
        $session       = Mockery::mock();
        $session->shouldReceive('set')->andReturnUsing(
            static function (string $key, $value) use (&$sessionWrites): void {
                $sessionWrites[] = [$key, $value];
            },
        );
        $this->mockSession($session);

        $item->shouldReceive('add_meta_data')->once()->with(MetaKeys::OFFICE_CODE, 'NSK17', true);
        $item->shouldReceive('save')->once();

        CheckoutMapBlock::saveOrderData($order, $this->request('NSK17'));

        self::assertSame([['official_cdek_office_code', 'NSK17']], $sessionWrites);
    }

    public function testSaveOrderDataClearsOfficeForDoorTariff(): void
    {
        [$order, $item] = $this->orderWithShipping(139);

        $session = Mockery::mock();
        $session->shouldReceive('set');
        $this->mockSession($session);

        $item->shouldReceive('add_meta_data')->once()->with(MetaKeys::OFFICE_CODE, null, true);
        $item->shouldReceive('save')->once();

        CheckoutMapBlock::saveOrderData($order, $this->request('NSK17'));

        $this->addToAssertionCount(1);
    }

    public function testSaveOrderDataStoresNullOfficeWhenRequestHasNoExtension(): void
    {
        [$order, $item] = $this->orderWithShipping(136);

        $session = Mockery::mock();
        $session->shouldReceive('set');
        $this->mockSession($session);

        $item->shouldReceive('add_meta_data')->once()->with(MetaKeys::OFFICE_CODE, null, true);
        $item->shouldReceive('save')->once();

        CheckoutMapBlock::saveOrderData($order, $this->request(null));

        $this->addToAssertionCount(1);
    }

    public function testSaveOrderDataStillSavesOfficeWhenSessionFails(): void
    {
        [$order, $item] = $this->orderWithShipping(136);

        $session = Mockery::mock();
        $session->shouldReceive('set')->andThrow(new RuntimeException('no session'));
        $this->mockSession($session);

        $item->shouldReceive('add_meta_data')->once()->with(MetaKeys::OFFICE_CODE, 'NSK17', true);
        $item->shouldReceive('save')->once();

        CheckoutMapBlock::saveOrderData($order, $this->request('NSK17'));

        $this->addToAssertionCount(1);
    }

    // ---- интеграция с блоками ----

    public function testIntegrationDescribesItself(): void
    {
        $block = new CheckoutMapBlock();

        self::assertSame('official_cdek', $block->get_name());
        self::assertSame(['cdek-checkout-map-block-frontend'], $block->get_script_handles());
        self::assertSame(['cdek-checkout-map-block-editor'], $block->get_editor_script_handles());
    }

    /**
     * @dataProvider localeProvider
     */
    public function testScriptDataContainsLanguageMapKeyAndOfficeModes(string $locale, string $expectedLang): void
    {
        Functions\when('get_user_locale')->justReturn($locale);

        $settings                     = Mockery::mock();
        $settings->yandex_map_api_key = 'yandex-key';
        Mockery::mock('alias:' . ShippingMethod::class)->shouldReceive('factory')->andReturn($settings);

        self::assertSame(
            [
                'lang'                => $expectedLang,
                'apiKey'              => 'yandex-key',
                'officeDeliveryModes' => Tariff::listOfficeDeliveryModes(),
            ],
            (new CheckoutMapBlock())->get_script_data(),
        );
    }

    public static function localeProvider(): array
    {
        return [
            'russian'         => ['ru_RU', 'rus'],
            'english us'      => ['en_US', 'eng'],
            'english uk'      => ['en_GB', 'eng'],
            'other language'  => ['de_DE', 'rus'],
            'contains en'     => ['ben_BD', 'rus'],
        ];
    }

    public function testInitializeEnqueuesFrontendAndEditorScripts(): void
    {
        $calls = [];
        Mockery::mock('alias:' . UI::class)->shouldReceive('enqueueScript')->andReturnUsing(
            static function (...$args) use (&$calls): void {
                $calls[] = $args;
            },
        );

        (new CheckoutMapBlock())->initialize();

        self::assertSame(
            [
                ['cdek-checkout-map-block-frontend', 'cdek-checkout-map-block-frontend', false, true],
                ['cdek-checkout-map-block-editor', 'cdek-checkout-map-block', false, true],
            ],
            $calls,
        );
    }
}
