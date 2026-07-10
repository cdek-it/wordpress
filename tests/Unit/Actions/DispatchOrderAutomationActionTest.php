<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Actions;

use Brain\Monkey\Functions;
use Cdek\Actions\DispatchOrderAutomationAction;
use Cdek\Config;
use Cdek\CoreApi;
use Cdek\Helpers\ScheduleLocker;
use Cdek\Model\Order;
use Cdek\Model\ShippingItem;
use Cdek\Note;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Exception;
use Mockery;
use Mockery\MockInterface;

/**
 * `Order` мокается через `overload:` (как и в OrderCreateActionTest - смешивать
 * режимы для одного класса между файлами нельзя, `overload:`/`alias:` конфликтуют
 * при совместном запуске сьюта).
 */
final class DispatchOrderAutomationActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('esc_html__')->returnArg();
    }

    /**
     * @return array{0: DispatchOrderAutomationAction, 1: MockInterface, 2: MockInterface, 3: MockInterface}
     */
    private function buildActionWithMocks(
        bool $isCancelled = false,
        bool $lockAcquired = true
    ): array {
        $shippingMethod                         = Mockery::mock('alias:' . ShippingMethod::class);
        $shippingMethod->automate_orders        = true;
        $shippingMethod->automate_wait_gateways = [];

        $shipping = Mockery::mock('alias:' . ShippingItem::class);
        $shipping->shouldReceive('getMethod')->andReturn($shippingMethod);

        $order = Mockery::mock('overload:' . Order::class);
        $order->shouldReceive('getShipping')->andReturn($shipping);
        $order->shouldReceive('isCancelled')->andReturn($isCancelled);
        // isPaid() принципиально не влияет на исход здесь - см. класс-докблок,
        // payment_method всегда читается как null, поэтому ветка ожидания оплаты
        // по конкретному шлюзу никогда не блокирует выполнение в этих тестах.
        $order->shouldReceive('isPaid')->andReturn(true);

        $scheduleLocker = Mockery::mock('alias:' . ScheduleLocker::class);
        $scheduleLocker->shouldReceive('instance')->andReturn($scheduleLocker);
        $scheduleLocker->shouldReceive('set')->with(Mockery::any())->andReturn($lockAcquired);

        $action = new DispatchOrderAutomationAction();

        return [$action, $order, $shipping, $shippingMethod];
    }

    public function testInvokeDoesNothingWhenOrderHasNoShipping(): void
    {
        // Ни ScheduleLocker, ни CoreApi не должны быть замоканы - если экшен всё
        // же дойдёт до них, немокнутый вызов упадёт с BadMethodCallException.
        $order = Mockery::mock('overload:' . Order::class);
        $order->shouldReceive('getShipping')->andReturn(null);

        @(new DispatchOrderAutomationAction())(123);

        self::assertTrue(true);
    }

    public function testInvokeDoesNothingWhenAutomationIsDisabledForMethod(): void
    {
        [$action, , , $shippingMethod] = $this->buildActionWithMocks();
        $shippingMethod->automate_orders = false;

        @$action(123);

        self::assertTrue(true);
    }

    public function testInvokeDoesNothingWhenOrderIsCancelled(): void
    {
        [$action] = $this->buildActionWithMocks(true);

        @$action(123);

        self::assertTrue(true);
    }

    public function testInvokeDoesNothingWhenScheduleLockCannotBeAcquired(): void
    {
        [$action] = $this->buildActionWithMocks(false, false);

        // CoreApi не должен быть замокан вовсе - если экшен всё же дойдёт до него,
        // немокнутый вызов упадёт сам.
        @$action(123);

        self::assertTrue(true);
    }

    public function testInvokeProceedsWhenNoGatewaysAwaitPayment(): void
    {
        [$action] = $this->buildActionWithMocks();

        Mockery::mock('overload:' . CoreApi::class)
            ->shouldReceive('orderGet')->once()->andReturn(null);

        @$action(123);

        self::assertTrue(true);
    }

    public function testInvokeSkipsSchedulingWhenOrderAlreadyExistsRemotely(): void
    {
        [$action] = $this->buildActionWithMocks();

        Mockery::mock('overload:' . CoreApi::class)
            ->shouldReceive('orderGet')->once()->andReturn(null);

        // Ни as_schedule_single_action, ни Note::send не должны понадобиться -
        // если экшен всё же зайдёт в catch-блок, немокнутый вызов упадёт сам.
        @$action(123);

        self::assertTrue(true);
    }

    public function testInvokeSchedulesRetryAndSendsNoteWhenOrderGetThrows(): void
    {
        [$action] = $this->buildActionWithMocks();

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

        @$action(123);

        self::assertTrue(true);
    }

    public function testInvokeSchedulesRetryButSkipsNoteWhenSchedulingFails(): void
    {
        [$action] = $this->buildActionWithMocks();

        Mockery::mock('overload:' . CoreApi::class)
            ->shouldReceive('orderGet')->once()->andThrow(new Exception('not found'));

        Functions\expect('as_schedule_single_action')->once()->andReturn(false);

        // Note::send() не должен вызываться - если всё же вызовется, немокнутый
        // alias упадёт с BadMethodCallException.
        Mockery::mock('alias:' . Note::class);

        @$action(123);

        self::assertTrue(true);
    }
}
