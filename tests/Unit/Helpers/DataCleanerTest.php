<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Helpers;

use Brain\Monkey\Functions;
use Cdek\Helpers\DataCleaner;
use Cdek\MetaKeys;
use Cdek\Model\Order;
use Cdek\Tests\TestCase;
use Mockery;

final class DataCleanerTest extends TestCase
{
    private const BASE_HIDDEN_META
        = [
            MetaKeys::ADDRESS_HASH,
            MetaKeys::WEIGHT,
            MetaKeys::LENGTH,
            MetaKeys::WIDTH,
            MetaKeys::HEIGHT,
            MetaKeys::TARIFF_CODE,
            MetaKeys::TARIFF_MODE,
            MetaKeys::OFFICE_CODE,
            MetaKeys::JEWEL_UIN,
            MetaKeys::POSTAL,
            MetaKeys::CITY,
        ];

    private const ORDER_PAGE_HIDDEN_META
        = [
            'tariff_code',
            'tariff_type',
            'tariff_mode',
            'length',
            'width',
            'height',
            'pvz',
            'weight',
            'weight (kg)',
            'weight (g)',
            'weight (lbs)',
            'weight (oz)',
        ];

    /** @var array */
    private $originalRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalRequest = $_REQUEST;
        $_REQUEST              = [];

        Functions\when('wp_strip_all_tags')->returnArg();
        Functions\when('absint')->alias(static fn($value = 0) => abs((int)$value));
    }

    protected function tearDown(): void
    {
        $_REQUEST = $this->originalRequest;

        parent::tearDown();
    }

    private function mockOrderShipping(?object $shipping): void
    {
        Mockery::mock('overload:' . Order::class)
               ->shouldReceive('getShipping')
               ->once()
               ->andReturn($shipping);
    }

    public function testGetDataMapsEachRequestedParamToItsValue(): void
    {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_param')->with('name')->andReturn('Иван');
        $request->shouldReceive('get_param')->with('phone')->andReturn('+79991234567');

        $result = DataCleaner::getData($request, ['name', 'phone']);

        self::assertSame(['name' => 'Иван', 'phone' => '+79991234567'], $result);
    }

    public function testGetDataReturnsEmptyArrayForEmptyParamList(): void
    {
        $request = Mockery::mock('WP_REST_Request');

        $result = DataCleaner::getData($request, []);

        self::assertSame([], $result);
    }

    public function testHideMetaKeepsOriginalEntriesInFrontAndAppendsBaseKeysWhenNotOnOrderPage(): void
    {
        $result = DataCleaner::hideMeta(['custom_existing_meta']);

        self::assertSame(
            array_merge(['custom_existing_meta'], self::BASE_HIDDEN_META),
            $result,
        );
    }

    public function testHideMetaDoesNotTouchOrderWhenRequestHasNoOrderPageMarkers(): void
    {
        $result = DataCleaner::hideMeta([]);

        self::assertSame(self::BASE_HIDDEN_META, $result);
    }

    public function testHideMetaAppendsOrderPageKeysOnOldOrderPageWhenShippingIsSet(): void
    {
        $_REQUEST = ['page' => 'wc-orders', 'action' => 'edit', 'id' => '123'];
        $this->mockOrderShipping(Mockery::mock());

        $result = DataCleaner::hideMeta([]);

        self::assertSame(
            array_merge(self::BASE_HIDDEN_META, self::ORDER_PAGE_HIDDEN_META),
            $result,
        );
    }

    public function testHideMetaSkipsOrderPageKeysOnOldOrderPageWhenShippingIsNull(): void
    {
        $_REQUEST = ['page' => 'wc-orders', 'action' => 'edit', 'id' => '123'];
        $this->mockOrderShipping(null);

        $result = DataCleaner::hideMeta([]);

        self::assertSame(self::BASE_HIDDEN_META, $result);
    }

    public function testHideMetaDoesNotMatchOldOrderPageWhenActionDiffers(): void
    {
        $_REQUEST = ['page' => 'wc-orders', 'action' => 'trash', 'id' => '123'];

        $result = DataCleaner::hideMeta([]);

        self::assertSame(self::BASE_HIDDEN_META, $result);
    }

    public function testHideMetaAppendsOrderPageKeysOnNewOrderPageWhenShippingIsSet(): void
    {
        $_REQUEST = ['action' => 'woocommerce_load_order_items', 'order_id' => '123'];
        $this->mockOrderShipping(Mockery::mock());

        $result = DataCleaner::hideMeta([]);

        self::assertSame(
            array_merge(self::BASE_HIDDEN_META, self::ORDER_PAGE_HIDDEN_META),
            $result,
        );
    }

    public function testHideMetaSkipsOrderPageKeysOnNewOrderPageWhenShippingIsNull(): void
    {
        $_REQUEST = ['action' => 'woocommerce_load_order_items', 'order_id' => '123'];
        $this->mockOrderShipping(null);

        $result = DataCleaner::hideMeta([]);

        self::assertSame(self::BASE_HIDDEN_META, $result);
    }

    public function testHideMetaDoesNotMatchNewOrderPageWhenOrderIdIsMissing(): void
    {
        $_REQUEST = ['action' => 'woocommerce_load_order_items'];

        $result = DataCleaner::hideMeta([]);

        self::assertSame(self::BASE_HIDDEN_META, $result);
    }
}
