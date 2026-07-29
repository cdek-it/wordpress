<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Model;

use Brain\Monkey\Functions;
use Cdek\CdekApi;
use Cdek\Config;
use Cdek\Exceptions\OrderNotFoundException;
use Cdek\Loader;
use Cdek\Model\Order;
use Cdek\Model\ShippingItem;
use Cdek\Tests\TestCase;
use Cdek\Transport\HttpResponse;
use DateTimeImmutable;
use DateTimeInterface;
use Mockery;
use Mockery\MockInterface;
use ReflectionProperty;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class OrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Exception-конструкторы (через ExceptionContract) читают Loader::$pluginName,
        // которое в реальном плагине инициализируется при загрузке - в тестах его
        // никто не выставляет, поэтому делаем это вручную рефлексией.
        $pluginName = new ReflectionProperty(Loader::class, 'pluginName');
        $pluginName->setAccessible(true);
        $pluginName->setValue(null, 'cdekdelivery');

        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();
    }

    private function mockWcOrder(array $meta = []): MockInterface
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->once()->with('order_data')->andReturn($meta);

        return $order;
    }

    private function mockShippingWcItem(
        string $methodId = Config::DELIVERY_NAME,
        int $instanceId = 5
    ): MockInterface {
        $item = Mockery::mock('WC_Order_Item_Shipping');
        $item->shouldReceive('get_method_id')->andReturn($methodId);
        $item->shouldReceive('get_data')->andReturn(['instance_id' => $instanceId]);
        $item->shouldReceive('get_meta_data')->andReturn([]);

        return $item;
    }

    public function testConstructorAcceptsWcOrderInstanceDirectlyAndLoadsMeta(): void
    {
        $order = $this->mockWcOrder(['number' => 'ORD-1']);

        $model = new Order($order);

        self::assertSame('ORD-1', $model->number);
    }

    public function testConstructorLooksUpOrderByIdWhenGivenInt(): void
    {
        $order = $this->mockWcOrder(['number' => 'ORD-2']);

        Functions\expect('wc_get_order')->once()->with(42)->andReturn($order);

        $model = new Order(42);

        self::assertSame('ORD-2', $model->number);
    }

    public function testConstructorThrowsOrderNotFoundExceptionWhenWcGetOrderReturnsFalse(): void
    {
        Functions\expect('wc_get_order')->once()->with(999)->andReturn(false);

        $this->expectException(OrderNotFoundException::class);

        new Order(999);
    }

    public function testIsPaidReturnsTrueWhenOrderIsPaid(): void
    {
        $order = $this->mockWcOrder();
        $order->shouldReceive('is_paid')->once()->andReturn(true);

        $model = new Order($order);

        self::assertTrue($model->isPaid());
    }

    public function testIsPaidReturnsFalseWhenOrderIsNotPaid(): void
    {
        $order = $this->mockWcOrder();
        $order->shouldReceive('is_paid')->once()->andReturn(false);

        $model = new Order($order);

        self::assertFalse($model->isPaid());
    }

    public function testIsCancelledReturnsTrueWhenOrderHasCancelledStatus(): void
    {
        $order = $this->mockWcOrder();
        $order->shouldReceive('has_status')->once()->with('cancelled')->andReturn(true);

        $model = new Order($order);

        self::assertTrue($model->isCancelled());
    }

    public function testIsCancelledReturnsFalseWhenOrderDoesNotHaveCancelledStatus(): void
    {
        $order = $this->mockWcOrder();
        $order->shouldReceive('has_status')->once()->with('cancelled')->andReturn(false);

        $model = new Order($order);

        self::assertFalse($model->isCancelled());
    }

    public function testGetShippingReturnsNullWhenNoShippingMethodBelongsToPlugin(): void
    {
        $order = $this->mockWcOrder();
        $order->shouldReceive('get_shipping_methods')->once()
            ->andReturn([$this->mockShippingWcItem('flat_rate')]);

        $model = new Order($order);

        self::assertNull($model->getShipping());
    }

    public function testGetShippingReturnsShippingItemForFirstMatchingMethodAndSkipsInvalidOnes(): void
    {
        $wrongItem = $this->mockShippingWcItem('flat_rate');
        $validItem = $this->mockShippingWcItem(Config::DELIVERY_NAME, 42);

        $order = $this->mockWcOrder();
        $order->shouldReceive('get_shipping_methods')->once()->andReturn([$wrongItem, $validItem]);

        $model    = new Order($order);
        $shipping = $model->getShipping();

        self::assertInstanceOf(ShippingItem::class, $shipping);
        self::assertSame(42, $shipping->getInstanceId());
    }

    public function testGetShippingCachesResultAndFetchesShippingMethodsOnlyOnce(): void
    {
        $order = $this->mockWcOrder();
        $order->shouldReceive('get_shipping_methods')->once()
            ->andReturn([$this->mockShippingWcItem()]);

        $model = new Order($order);

        $first  = $model->getShipping();
        $second = $model->getShipping();

        self::assertSame($first, $second);
    }

    public function testShouldBePaidUponDeliveryReturnsTrueForCodPaymentMethod(): void
    {
        $order = $this->mockWcOrder();
        $order->shouldReceive('get_payment_method')->once()->andReturn('cod');

        $model = new Order($order);

        self::assertTrue($model->shouldBePaidUponDelivery());
    }

    public function testShouldBePaidUponDeliveryReturnsFalseForOtherPaymentMethod(): void
    {
        $order = $this->mockWcOrder();
        $order->shouldReceive('get_payment_method')->once()->andReturn('bacs');

        $model = new Order($order);

        self::assertFalse($model->shouldBePaidUponDelivery());
    }

    public function testLoadLegacyStatusesReturnsEmptyArrayWhenNumberIsEmpty(): void
    {
        $order = $this->mockWcOrder();

        $model = new Order($order);

        self::assertSame([], $model->loadLegacyStatuses());
    }

    public function testLoadLegacyStatusesUsesProvidedStatusesArrayWithoutCallingApi(): void
    {
        $order = $this->mockWcOrder(['number' => '12345']);

        $model = new Order($order);

        $result = $model->loadLegacyStatuses([
            ['date_time' => '2024-01-01T10:00:00+00:00', 'name' => 'Created', 'code' => 'CREATED'],
        ]);

        self::assertCount(1, $result);
        self::assertSame('Created', $result[0]['name']);
        self::assertSame('CREATED', $result[0]['code']);
        self::assertEquals(
            DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, '2024-01-01T10:00:00+00:00'),
            $result[0]['time'],
        );
    }

    public function testLoadLegacyStatusesFetchesFromApiWhenStatusesArgumentIsNull(): void
    {
        $order = $this->mockWcOrder(['number' => '12345']);

        $httpResponse = new HttpResponse(
            200,
            json_encode([
                'entity' => [
                    'statuses' => [
                        ['date_time' => '2024-01-02T08:00:00+00:00', 'name' => 'Accepted', 'code' => 'ACCEPTED'],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            ['content-type' => 'application/json'],
            'https://example.test/orders',
            'GET',
        );

        $cdekApi = Mockery::mock('overload:' . CdekApi::class);
        $cdekApi->shouldReceive('orderGetByNumber')->once()->with('12345')->andReturn($httpResponse);

        $model  = new Order($order);
        $result = $model->loadLegacyStatuses();

        self::assertSame('ACCEPTED', $result[0]['code']);
    }

    public function testLoadLegacyStatusesThrowsOrderNotFoundExceptionWhenApiEntityIsNull(): void
    {
        $order = $this->mockWcOrder(['number' => '12345']);

        $httpResponse = new HttpResponse(
            200,
            json_encode([], JSON_THROW_ON_ERROR),
            ['content-type' => 'application/json'],
            'https://example.test/orders',
            'GET',
        );

        $cdekApi = Mockery::mock('overload:' . CdekApi::class);
        $cdekApi->shouldReceive('orderGetByNumber')->once()->with('12345')->andReturn($httpResponse);

        $model = new Order($order);

        $this->expectException(OrderNotFoundException::class);

        $model->loadLegacyStatuses();
    }

    /**
     * @dataProvider lockedStatusProvider
     */
    public function testLoadLegacyStatusesSetsLockedBasedOnFirstStatusCode(
        string $code,
        bool $expectedLocked
    ): void {
        $order = $this->mockWcOrder(['number' => '12345']);

        $model = new Order($order);
        $model->loadLegacyStatuses([
            ['date_time' => '2024-01-01T10:00:00+00:00', 'name' => 'Some status', 'code' => $code],
        ]);

        self::assertSame($expectedLocked, $model->isLocked());
    }

    public static function lockedStatusProvider(): array
    {
        return [
            'CREATED keeps order unlocked' => ['CREATED', false],
            'INVALID keeps order unlocked' => ['INVALID', false],
            'any other code locks order'   => ['ACCEPTED', true],
        ];
    }

    /**
     * @dataProvider legacyAliasProvider
     */
    public function testGetMigratesLegacyAliasAndPersistsViaSave(
        string $legacyKey,
        string $property,
        string $value
    ): void {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->once()->with('order_data')->andReturn([$legacyKey => $value]);
        $order->shouldReceive('update_meta_data')->once()->with('order_data', [$property => $value]);
        $order->shouldReceive('save')->once();

        $model = new Order($order);

        self::assertSame($value, $model->$property);
    }

    public static function legacyAliasProvider(): array
    {
        return [
            'uuid <- order_uuid'     => ['order_uuid', 'uuid', 'legacy-uuid-1'],
            'number <- order_number' => ['order_number', 'number', 'LEGACY-NUM-1'],
        ];
    }
}
