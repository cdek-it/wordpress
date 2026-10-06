<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Tasks;

use Brain\Monkey\Functions;
use Cdek\CoreApi;
use Cdek\Exceptions\ScheduledTaskException;
use Cdek\Loader;
use Cdek\Model\TaskResult;
use Cdek\Tasks\RestoreOrderUuids;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use ReflectionProperty;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class RestoreOrderUuidsTest extends TestCase
{
    private const TASK_ID = 'task-1';

    protected function setUp(): void
    {
        parent::setUp();

        // esc_html__ нужен конструкторам OrderNotFoundException/ScheduledTaskException.
        Functions\when('esc_html__')->returnArg();

        // Logger::debug() ничего не делает, пока wc_get_logger() не вернул логгер.
        Functions\when('wc_get_logger')->justReturn(null);

        // Loader::getPluginName() нужен конструктору исключений.
        $property = new ReflectionProperty(Loader::class, 'pluginName');
        $property->setAccessible(true);
        $property->setValue(null, 'CDEK');
    }

    private function mockCoreApi(?array $taskMeta): MockInterface
    {
        $api = Mockery::mock('overload:' . CoreApi::class);
        $api->shouldReceive('taskGet')->with(self::TASK_ID)->andReturn(['meta' => $taskMeta]);

        return $api;
    }

    /**
     * @param array<int, MockInterface|false> $orders WC_Order по id или false, если заказа нет
     */
    private function mockOrderLookup(array $orders): void
    {
        Functions\when('wc_get_order')->alias(static fn($id) => $orders[$id] ?? false);
    }

    private function wcOrder(array $existingMeta, string $expectedUuid): MockInterface
    {
        $wcOrder = Mockery::mock('WC_Order');
        $wcOrder->shouldReceive('get_meta')->with('order_data')->andReturn($existingMeta);
        $wcOrder->shouldReceive('update_meta_data')
                ->once()
                ->with('order_data', array_merge($existingMeta, ['uuid' => $expectedUuid]));
        $wcOrder->shouldReceive('save')->once();

        return $wcOrder;
    }

    private function expectResult(MockInterface $api, string $status, ?array $data = null): void
    {
        $api->shouldReceive('taskResultCreate')
            ->once()
            ->with(
                self::TASK_ID,
                Mockery::on(static fn($result) => $result instanceof TaskResult
                                                  && $result->getStatus() === $status
                                                  && $result->getData() === $data),
            );
    }

    public function testGetNameReturnsRestoreOrderUuids(): void
    {
        self::assertSame('restore-order-uuids', RestoreOrderUuids::getName());
    }

    public function testThrowsScheduledTaskExceptionWhenTaskMetaIsEmpty(): void
    {
        $api = $this->mockCoreApi(null);
        $api->shouldReceive('taskResultCreate')->never();

        $this->expectException(ScheduledTaskException::class);

        (new RestoreOrderUuids())(self::TASK_ID);
    }

    public function testRestoresUuidsForAllOrdersAndReportsSuccess(): void
    {
        $this->mockOrderLookup([
            101 => $this->wcOrder(['number' => 'N1'], 'uuid-1'),
            102 => $this->wcOrder([], 'uuid-2'),
        ]);

        $api = $this->mockCoreApi([
            ['external_id' => 101, 'id' => 'uuid-1'],
            ['external_id' => 102, 'id' => 'uuid-2'],
        ]);
        $this->expectResult($api, 'success');

        (new RestoreOrderUuids())(self::TASK_ID);

        $this->addToAssertionCount(1);
    }

    public function testReportsWarningWithFailedIdsAndStillRestoresFoundOrders(): void
    {
        $this->mockOrderLookup([
            101 => $this->wcOrder(['number' => 'N1'], 'uuid-1'),
            103 => $this->wcOrder([], 'uuid-3'),
        ]);

        $api = $this->mockCoreApi([
            ['external_id' => 101, 'id' => 'uuid-1'],
            ['external_id' => 999, 'id' => 'uuid-missing'],
            ['external_id' => 103, 'id' => 'uuid-3'],
            ['external_id' => 998, 'id' => 'uuid-missing-2'],
        ]);
        $this->expectResult($api, 'warning', ['failed' => [999, 998]]);

        (new RestoreOrderUuids())(self::TASK_ID);

        $this->addToAssertionCount(1);
    }

    public function testReportsWarningWhenNoOrderIsFound(): void
    {
        $this->mockOrderLookup([]);

        $api = $this->mockCoreApi([
            ['external_id' => 999, 'id' => 'uuid-1'],
        ]);
        $this->expectResult($api, 'warning', ['failed' => [999]]);

        (new RestoreOrderUuids())(self::TASK_ID);

        $this->addToAssertionCount(1);
    }

    public function testReportsSuccessWhenThereAreNoOrdersToRestore(): void
    {
        $this->mockOrderLookup([]);

        $api = $this->mockCoreApi([]);
        $this->expectResult($api, 'success');

        (new RestoreOrderUuids())(self::TASK_ID);

        $this->addToAssertionCount(1);
    }
}
