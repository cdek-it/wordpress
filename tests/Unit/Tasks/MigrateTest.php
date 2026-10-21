<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Tasks;

use Cdek\CoreApi;
use Cdek\Loader;
use Cdek\Migrators\MigrateCityCodeFromMap;
use Cdek\Model\TaskResult;
use Cdek\Tasks\Migrate;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class MigrateTest extends TestCase
{
    private const TASK_ID = 'task-1';

    /** @var int */
    public static int $migratorCalls = 0;
    /** @var \Closure|null */
    public static ?\Closure $migratorBehavior = null;

    protected function setUp(): void
    {
        parent::setUp();

        self::$migratorCalls    = 0;
        self::$migratorBehavior = null;

        if (!class_exists(MigrateCityCodeFromMap::class, false)) {
            eval(
                'namespace Cdek\\Migrators; class MigrateCityCodeFromMap {'
                . ' public function __invoke(): void {'
                . ' \\' . self::class . '::$migratorCalls++;'
                . ' if (\\' . self::class . '::$migratorBehavior !== null) { (\\' . self::class . '::$migratorBehavior)(); }'
                . ' } }'
            );
        }
    }

    private function mockCoreApi(): MockInterface
    {
        $api = Mockery::mock('overload:' . CoreApi::class);
        $api->shouldReceive('taskGet')->with(self::TASK_ID)->andReturn(['meta' => []]);

        return $api;
    }

    public function testGetNameReturnsMigrateConnector(): void
    {
        self::assertSame('migrate-connector', Migrate::getName());
    }

    public function testMigratorsRegisteredInLoaderIncludeCityCodeMigration(): void
    {
        self::assertContains(MigrateCityCodeFromMap::class, Loader::MIGRATORS);
    }

    public function testRunsMigratorsAndReportsSuccessAfterwards(): void
    {
        $api = $this->mockCoreApi();

        $api->shouldReceive('taskResultCreate')
            ->once()
            ->with(
                self::TASK_ID,
                Mockery::on(static fn($result) => $result instanceof TaskResult
                                                  && $result->getStatus() === 'success'
                                                  && $result->getData() === null
                                                  && $result->getCurrentPage() === null
                                                  && $result->getTotalPages() === null),
            )
            ->andReturnUsing(static function () {
                self::assertSame(1, self::$migratorCalls);
            });

        (new Migrate())(self::TASK_ID);

        self::assertSame(1, self::$migratorCalls);
    }

    public function testDoesNotReportResultWhenMigratorFails(): void
    {
        $api = $this->mockCoreApi();
        $api->shouldReceive('taskResultCreate')->never();

        self::$migratorBehavior = static function (): void {
            throw new RuntimeException('migration failed');
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('migration failed');

        (new Migrate())(self::TASK_ID);
    }
}
