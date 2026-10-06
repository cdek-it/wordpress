<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit;

use Brain\Monkey\Functions;
use Cdek\Config;
use Cdek\CoreApi;
use Cdek\TaskManager;
use Cdek\Tasks\CollectOrphanedOrders;
use Cdek\Tasks\Migrate;
use Cdek\Tasks\RestoreOrderUuids;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class TaskManagerTest extends TestCase
{
    private const GROUP = 'cdekdelivery';

    /** @var array<int, array{0: string, 1: mixed, 2: int}> */
    private array $addedActions = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->addedActions = [];

        Functions\when('add_action')->alias(
            function (string $hook, $callback, int $priority = 10): bool {
                $this->addedActions[] = [$hook, $callback, $priority];

                return true;
            },
        );
    }

    private function mockCoreApi(bool $hasToken): MockInterface
    {
        $api = Mockery::mock('overload:' . CoreApi::class);
        $api->shouldReceive('hasToken')->andReturn($hasToken);

        return $api;
    }

    private function page(array $tasks, ?string $nextCursor = null): MockInterface
    {
        $response = Mockery::mock();
        $response->shouldReceive('data')->andReturn($tasks);
        $response->shouldReceive('nextCursor')->andReturn($nextCursor);

        return $response;
    }

    private static function task(string $id, string $name, ?string $schedule = null): array
    {
        return ['id' => $id, 'name' => $name, 'schedule' => $schedule];
    }

    private static function hook(string $taskName): string
    {
        return Config::TASK_MANAGER_HOOK_NAME . '-' . $taskName;
    }

    public function testGetHookNameCombinesManagerHookWithTaskName(): void
    {
        self::assertSame(
            Config::TASK_MANAGER_HOOK_NAME . '-some-task',
            TaskManager::getHookName('some-task'),
        );
    }

    public function testConstructorRegistersEveryTaskUnderItsOwnHook(): void
    {
        new TaskManager();

        self::assertCount(3, $this->addedActions);

        $byHook = [];
        foreach ($this->addedActions as [$hook, $callback, $priority]) {
            $byHook[$hook] = [$callback, $priority];
        }

        $expected = [
            'restore-order-uuids'   => RestoreOrderUuids::class,
            'collect-orphaned-orders' => CollectOrphanedOrders::class,
            'migrate-connector'     => Migrate::class,
        ];

        foreach ($expected as $taskName => $class) {
            $hook = TaskManager::getHookName($taskName);

            self::assertArrayHasKey($hook, $byHook);
            self::assertInstanceOf($class, $byHook[$hook][0]);
            self::assertSame(20, $byHook[$hook][1]);
        }
    }

    public function testDoesNothingWithoutCoreToken(): void
    {
        $api   = $this->mockCoreApi(false);
        $calls = 0;
        $api->shouldReceive('taskList')->andReturnUsing(
            function () use (&$calls): MockInterface {
                $calls++;

                return $this->page([]);
            },
        );

        Functions\expect('as_enqueue_async_action')->never();
        Functions\expect('as_schedule_cron_action')->never();

        (new TaskManager())();

        self::assertSame(0, $calls);
    }

    public function testDoesNothingWhenThereAreNoTasks(): void
    {
        $api   = $this->mockCoreApi(true);
        $calls = 0;
        $api->shouldReceive('taskList')->andReturnUsing(
            function () use (&$calls): MockInterface {
                $calls++;

                return $this->page([]);
            },
        );

        Functions\expect('as_enqueue_async_action')->never();
        Functions\expect('as_schedule_cron_action')->never();

        (new TaskManager())();

        self::assertSame(1, $calls);
    }

    public function testSkipsTasksThatThisPluginDoesNotHandle(): void
    {
        $api = $this->mockCoreApi(true);
        $api->shouldReceive('taskList')->andReturn($this->page([self::task('t-1', 'unknown-task')]));

        Functions\expect('as_has_scheduled_action')->never();
        Functions\expect('as_enqueue_async_action')->never();
        Functions\expect('as_schedule_cron_action')->never();

        (new TaskManager())();

        $this->addToAssertionCount(1);
    }

    public function testEnqueuesOneOffTaskOnce(): void
    {
        $api = $this->mockCoreApi(true);
        $api->shouldReceive('taskList')->andReturn($this->page([self::task('t-1', 'migrate-connector')]));

        $hook = self::hook('migrate-connector');

        Functions\expect('as_has_scheduled_action')->once()->with($hook, ['t-1'], self::GROUP)->andReturn(false);
        Functions\expect('as_enqueue_async_action')->once()->with($hook, ['t-1'], self::GROUP);
        Functions\expect('as_schedule_cron_action')->never();

        (new TaskManager())();

        $this->addToAssertionCount(1);
    }

    public function testDoesNotEnqueueOneOffTaskThatIsAlreadyScheduled(): void
    {
        $api = $this->mockCoreApi(true);
        $api->shouldReceive('taskList')->andReturn($this->page([self::task('t-1', 'migrate-connector')]));

        Functions\expect('as_has_scheduled_action')
            ->once()
            ->with(self::hook('migrate-connector'), ['t-1'], self::GROUP)
            ->andReturn(true);
        Functions\expect('as_enqueue_async_action')->never();
        Functions\expect('as_schedule_cron_action')->never();

        (new TaskManager())();

        $this->addToAssertionCount(1);
    }

    public function testSchedulesRecurringTaskByItsCronExpression(): void
    {
        $api = $this->mockCoreApi(true);
        $api->shouldReceive('taskList')->andReturn(
            $this->page([self::task('t-2', 'collect-orphaned-orders', '30 4 * * *')]),
        );

        Functions\expect('as_has_scheduled_action')->never();
        Functions\expect('as_enqueue_async_action')->never();
        Functions\expect('as_schedule_cron_action')
            ->once()
            ->withArgs(
                static fn($timestamp, $schedule, $hook, $args, $group, $unique): bool
                    => $timestamp >= time() + 59 && $timestamp <= time() + 61
                       && $schedule === '30 4 * * *'
                       && $hook === self::hook('collect-orphaned-orders')
                       && $args === ['t-2']
                       && $group === self::GROUP
                       && $unique === true,
            );

        (new TaskManager())();

        $this->addToAssertionCount(1);
    }

    public function testLoadsAllPagesAndProcessesTasksFromEachOfThem(): void
    {
        $api = $this->mockCoreApi(true);

        $pages = [
            $this->page([self::task('t-1', 'migrate-connector')], 'cursor-2'),
            $this->page([self::task('t-2', 'restore-order-uuids')], 'cursor-3'),
            $this->page([self::task('t-3', 'unknown-task'), self::task('t-4', 'restore-order-uuids')]),
        ];
        $cursors = [];
        $api->shouldReceive('taskList')->andReturnUsing(
            static function (?string $cursor = null) use (&$cursors, &$pages): MockInterface {
                $cursors[] = $cursor;

                return array_shift($pages);
            },
        );

        Functions\when('as_has_scheduled_action')->justReturn(false);

        $enqueued = [];
        Functions\when('as_enqueue_async_action')->alias(
            static function (string $hook, array $args) use (&$enqueued): void {
                $enqueued[] = [$hook, $args];
            },
        );

        (new TaskManager())();

        self::assertSame([null, 'cursor-2', 'cursor-3'], $cursors);
        self::assertSame(
            [
                [self::hook('migrate-connector'), ['t-1']],
                [self::hook('restore-order-uuids'), ['t-2']],
                [self::hook('restore-order-uuids'), ['t-4']],
            ],
            $enqueued,
        );
    }
}
