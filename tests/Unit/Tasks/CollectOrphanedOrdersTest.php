<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Tasks;

use Brain\Monkey\Functions;
use Cdek\CoreApi;
use Cdek\Model\TaskResult;
use Cdek\Tasks\CollectOrphanedOrders;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use stdClass;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class CollectOrphanedOrdersTest extends TestCase
{
    private const TASK_ID = 'task-1';

    /** @var TaskResult[] */
    private array $results = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->results = [];

        Functions\when('wc_get_logger')->justReturn(null);

        $api = Mockery::mock('overload:' . CoreApi::class);
        $api->shouldReceive('taskGet')->with(self::TASK_ID)->andReturn(['meta' => null]);
        $api->shouldReceive('taskResultCreate')->andReturnUsing(
            function (string $id, TaskResult $result): void {
                self::assertSame(self::TASK_ID, $id);

                $this->results[] = $result;
            },
        );
    }

    /**
     * @param array<int, array<int, int>> $pages заказы по страницам (номер страницы с 1)
     */
    private function mockQuery(array $pages, int $maxPages): MockInterface
    {
        $query = Mockery::mock('overload:WC_Order_Query');

        $currentPage = 0;
        $query->shouldReceive('set')->andReturnUsing(
            static function (string $key, $value) use (&$currentPage): void {
                self::assertSame('page', $key);

                $currentPage = $value;
            },
        );
        $query->shouldReceive('get_orders')->andReturnUsing(
            static function () use (&$currentPage, $pages, $maxPages): stdClass {
                $result                = new stdClass();
                $result->max_num_pages = $maxPages;
                $result->orders        = $pages[$currentPage] ?? [];

                return $result;
            },
        );

        return $query;
    }

    public function testGetNameReturnsCollectOrphanedOrders(): void
    {
        self::assertSame('collect-orphaned-orders', CollectOrphanedOrders::getName());
    }

    public function testQueriesOrderIdsInBatchesOrderedById(): void
    {
        $query = $this->mockQuery([1 => []], 1);
        $query->shouldReceive('__construct')->once()->with([
            'orderby'  => 'id',
            'order'    => 'ASC',
            'paginate' => true,
            'limit'    => 1000,
            'return'   => 'ids',
        ]);

        (new CollectOrphanedOrders())(self::TASK_ID);

        $this->addToAssertionCount(1);
    }

    public function testReportsSinglePageWithOrderIdsAsStrings(): void
    {
        $this->mockQuery([1 => [10, 11, 12]], 1);

        (new CollectOrphanedOrders())(self::TASK_ID);

        self::assertCount(1, $this->results);
        self::assertSame('success', $this->results[0]->getStatus());
        self::assertSame(['orders' => ['10', '11', '12']], $this->results[0]->getData());
        self::assertSame(1, $this->results[0]->getCurrentPage());
        self::assertSame(1, $this->results[0]->getTotalPages());
    }

    public function testReportsEveryPageInOrder(): void
    {
        $this->mockQuery([1 => [1, 2], 2 => [3, 4], 3 => [5]], 3);

        (new CollectOrphanedOrders())(self::TASK_ID);

        self::assertCount(3, $this->results);

        foreach ([[1, ['1', '2']], [2, ['3', '4']], [3, ['5']]] as $index => [$page, $orders]) {
            self::assertSame('success', $this->results[$index]->getStatus());
            self::assertSame(['orders' => $orders], $this->results[$index]->getData());
            self::assertSame($page, $this->results[$index]->getCurrentPage());
            self::assertSame(3, $this->results[$index]->getTotalPages());
        }
    }

    public function testReportsOneEmptyPageWhenThereAreNoOrders(): void
    {
        $this->mockQuery([], 0);

        (new CollectOrphanedOrders())(self::TASK_ID);

        self::assertCount(1, $this->results);
        self::assertSame(['orders' => []], $this->results[0]->getData());
        self::assertSame(1, $this->results[0]->getCurrentPage());
        self::assertSame(0, $this->results[0]->getTotalPages());
    }
}
