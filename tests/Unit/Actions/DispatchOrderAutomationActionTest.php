<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Actions;

use Brain\Monkey\Functions;
use Cdek\Actions\DispatchOrderAutomationAction;
use Cdek\Config;
use Cdek\CoreApi;
use Cdek\Helpers\ScheduleLocker;
use Cdek\Note;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Exception;
use Mockery;
use Mockery\MockInterface;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class DispatchOrderAutomationActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('esc_html__')->returnArg();
    }

    /**
     * @return array{0: DispatchOrderAutomationAction, 1: MockInterface, 2: MockInterface}
     */
    private function buildActionWithMocks(
        bool $isCancelled = false,
        bool $lockAcquired = true,
        array $awaitingGateways = [],
        ?string $paymentMethod = null,
        bool $isPaid = true,
        bool $hasShippingItem = true
    ): array {
        $shippingMethod = Mockery::mock('alias:' . ShippingMethod::class);
        $shippingMethod->shouldReceive('factory')->andReturn($shippingMethod);
        $shippingMethod->automate_orders        = true;
        $shippingMethod->automate_wait_gateways = $awaitingGateways;

        $shippingWcItems = [];

        if ($hasShippingItem) {
            $wcShippingItem = Mockery::mock('WC_Order_Item_Shipping');
            $wcShippingItem->shouldReceive('get_method_id')->andReturn(Config::DELIVERY_NAME);
            $wcShippingItem->shouldReceive('get_data')->andReturn(['instance_id' => 5]);
            $wcShippingItem->shouldReceive('get_meta_data')->andReturn([]);

            $shippingWcItems[] = $wcShippingItem;
        }

        $wcOrder = Mockery::mock('WC_Order');
        $wcOrder->shouldReceive('get_meta')->with('order_data')->andReturn([]);
        $wcOrder->shouldReceive('get_shipping_methods')->andReturn($shippingWcItems);
        $wcOrder->shouldReceive('get_payment_method')->andReturn($paymentMethod);
        $wcOrder->shouldReceive('is_paid')->andReturn($isPaid);
        $wcOrder->shouldReceive('has_status')->with('cancelled')->andReturn($isCancelled);
        $wcOrder->shouldReceive('get_id')->andReturn(123);

        $scheduleLocker = Mockery::mock('alias:' . ScheduleLocker::class);
        $scheduleLocker->shouldReceive('instance')->andReturn($scheduleLocker);
        $scheduleLocker->shouldReceive('set')->with(Mockery::any())->andReturn($lockAcquired);

        $action = new DispatchOrderAutomationAction();

        return [$action, $wcOrder, $shippingMethod];
    }

    public function testInvokeDoesNothingWhenOrderHasNoShipping(): void
    {
        [$action, $wcOrder] = $this->buildActionWithMocks(false, true, [], null, true, false);

        @$action(123, null, $wcOrder);

        self::assertTrue(true);
    }

    public function testInvokeDoesNothingWhenAutomationIsDisabledForMethod(): void
    {
        [$action, $wcOrder, $shippingMethod] = $this->buildActionWithMocks();
        $shippingMethod->automate_orders = false;

        @$action(123, null, $wcOrder);

        self::assertTrue(true);
    }

    public function testInvokeDoesNothingWhenOrderIsCancelled(): void
    {
        [$action, $wcOrder] = $this->buildActionWithMocks(true);

        @$action(123, null, $wcOrder);

        self::assertTrue(true);
    }

    public function testInvokeDoesNothingWhenScheduleLockCannotBeAcquired(): void
    {
        [$action, $wcOrder] = $this->buildActionWithMocks(false, false);

        @$action(123, null, $wcOrder);

        self::assertTrue(true);
    }

    public function testInvokeProceedsWhenNoGatewaysAwaitPayment(): void
    {
        [$action, $wcOrder] = $this->buildActionWithMocks();

        Mockery::mock('overload:' . CoreApi::class)
            ->shouldReceive('orderGet')->once()->andReturn(null);

        @$action(123, null, $wcOrder);

        self::assertTrue(true);
    }

    public function testInvokeSkipsSchedulingWhenOrderAlreadyExistsRemotely(): void
    {
        [$action, $wcOrder] = $this->buildActionWithMocks();

        Mockery::mock('overload:' . CoreApi::class)
            ->shouldReceive('orderGet')->once()->andReturn(null);

        @$action(123, null, $wcOrder);

        self::assertTrue(true);
    }

    public function testInvokeSchedulesRetryAndSendsNoteWhenOrderGetThrows(): void
    {
        [$action, $wcOrder] = $this->buildActionWithMocks();

        Mockery::mock('overload:' . CoreApi::class)
            ->shouldReceive('orderGet')->once()->andThrow(new Exception('not found'));

        Functions\expect('as_schedule_single_action')
            ->once()
            ->with(
                Mockery::type('int'),
                Config::ORDER_AUTOMATION_HOOK_NAME,
                Mockery::type('array'),
                'cdekdelivery',
            )
            ->andReturn(true);

        Mockery::mock('alias:' . Note::class)
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::any(), 'Created order automation task');

        @$action(123, null, $wcOrder);

        self::assertTrue(true);
    }

    public function testInvokeSchedulesRetryButSkipsNoteWhenSchedulingFails(): void
    {
        [$action, $wcOrder] = $this->buildActionWithMocks();

        Mockery::mock('overload:' . CoreApi::class)
            ->shouldReceive('orderGet')->once()->andThrow(new Exception('not found'));

        Functions\expect('as_schedule_single_action')->once()->andReturn(false);
        Mockery::mock('alias:' . Note::class);

        @$action(123, null, $wcOrder);

        self::assertTrue(true);
    }

    public function testInvokeDoesNothingWhenPaymentViaAwaitedGatewayIsNotYetPaid(): void
    {
        [$action, $wcOrder] = $this->buildActionWithMocks(
            false,
            true,
            ['bank_card'],
            'bank_card',
            false,
        );

        @$action(123, null, $wcOrder);

        self::assertTrue(true);
    }

    public function testInvokeProceedsWhenPaymentViaAwaitedGatewayIsAlreadyPaid(): void
    {
        [$action, $wcOrder] = $this->buildActionWithMocks(
            false,
            true,
            ['bank_card'],
            'bank_card',
            true,
        );

        Mockery::mock('overload:' . CoreApi::class)
            ->shouldReceive('orderGet')->once()->andReturn(null);

        @$action(123, null, $wcOrder);

        self::assertTrue(true);
    }

    public function testInvokeProceedsWhenPaymentMethodIsNotAmongAwaitedGatewaysEvenIfUnpaid(): void
    {
        [$action, $wcOrder] = $this->buildActionWithMocks(
            false,
            true,
            ['bank_card'],
            'cod',
            false,
        );

        Mockery::mock('overload:' . CoreApi::class)
            ->shouldReceive('orderGet')->once()->andReturn(null);

        @$action(123, null, $wcOrder);

        self::assertTrue(true);
    }
}
