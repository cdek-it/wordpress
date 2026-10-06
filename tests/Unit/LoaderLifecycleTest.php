<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit;

use Brain\Monkey\Functions;
use Cdek\Loader;
use Cdek\Migrators\MigrateCityCodeFromMap;
use Cdek\TaskManager;
use Cdek\Tests\TestCase;
use Mockery;
use RuntimeException;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LoaderLifecycleTest extends TestCase
{
    private const WP_PLUGIN_DIR = '/var/www/html/wp-content/plugins';

    /** @var string[] порядок событий: schedule / migrator / cancel */
    public static array $events = [];

    protected function setUp(): void
    {
        parent::setUp();

        self::$events = [];

        if (!defined('WP_PLUGIN_DIR')) {
            define('WP_PLUGIN_DIR', self::WP_PLUGIN_DIR);
        }

        if (!class_exists(MigrateCityCodeFromMap::class, false)) {
            eval(
                'namespace Cdek\\Migrators; class MigrateCityCodeFromMap {'
                . ' public function __invoke(): void { \\' . self::class . '::$events[] = "migrator"; } }'
            );
        }

        Functions\when('esc_html')->returnArg();
    }

    private function mockTaskManager(): void
    {
        $manager = Mockery::mock('alias:' . TaskManager::class);
        $manager->shouldReceive('scheduleExecution')->andReturnUsing(
            static function (): void {
                self::$events[] = 'schedule';
            },
        );
        $manager->shouldReceive('cancelExecution')->andReturnUsing(
            static function (): void {
                self::$events[] = 'cancel';
            },
        );
    }

    private function mockRequirements(
        bool $canActivate = true,
        array $activePlugins = ['woocommerce/woocommerce.php'],
        string $wooVersion = '9.0.0'
    ): void {
        Functions\when('current_user_can')->alias(
            static fn(string $capability): bool => $canActivate && $capability === 'activate_plugins',
        );
        Functions\when('get_option')->alias(
            static fn(string $name) => $name === 'active_plugins' ? $activePlugins : false,
        );
        Functions\when('get_file_data')->alias(
            static fn(string $file, array $headers): array => $file === self::WP_PLUGIN_DIR . '/woocommerce/woocommerce.php'
                                                              && $headers === ['Version'] ? [$wooVersion] : [''],
        );
    }

    public function testActivateDoesNothingWithoutPermission(): void
    {
        $this->mockRequirements(false);
        $this->mockTaskManager();

        Loader::activate();

        self::assertSame([], self::$events);
    }

    public function testActivateRunsUpgradeWhenRequirementsAreMet(): void
    {
        $this->mockRequirements();
        $this->mockTaskManager();

        Loader::activate();

        self::assertSame(['schedule', 'migrator'], self::$events);
    }

    public function testActivateAcceptsExactlyTheMinimumWooCommerceVersion(): void
    {
        $this->mockRequirements(true, ['woocommerce/woocommerce.php'], '6.9.0');
        $this->mockTaskManager();

        Loader::activate();

        self::assertSame(['schedule', 'migrator'], self::$events);
    }

    public function testActivateFailsWhenWooCommerceIsNotActive(): void
    {
        $this->mockRequirements(true, ['akismet/akismet.php']);
        $this->mockTaskManager();

        try {
            Loader::activate();
            self::fail('RuntimeException expected');
        } catch (RuntimeException $e) {
            self::assertSame('WooCommerce plugin is not activated, but required.', $e->getMessage());
        }

        self::assertSame([], self::$events);
    }

    public function testActivateFailsWhenWooCommerceIsTooOld(): void
    {
        $this->mockRequirements(true, ['woocommerce/woocommerce.php'], '6.8.9');
        $this->mockTaskManager();

        try {
            Loader::activate();
            self::fail('RuntimeException expected');
        } catch (RuntimeException $e) {
            self::assertSame(
                'WooCommerce plugin version is too old, required minimum version is 6.9.0.',
                $e->getMessage(),
            );
        }

        self::assertSame([], self::$events);
    }

    public function testActivateFailsWhenRequiredPhpExtensionIsMissing(): void
    {
        $this->mockRequirements();
        $this->mockTaskManager();
        Functions\when('extension_loaded')->alias(static fn(string $name): bool => $name !== 'openssl');

        try {
            Loader::activate();
            self::fail('RuntimeException expected');
        } catch (RuntimeException $e) {
            self::assertSame('openssl is not enabled, but required.', $e->getMessage());
        }

        self::assertSame([], self::$events);
    }

    public function testUpgradeSchedulesTasksBeforeRunningMigrators(): void
    {
        $this->mockTaskManager();

        Loader::upgrade();

        self::assertSame(['schedule', 'migrator'], self::$events);
    }

    public function testDeactivateCancelsScheduledTasks(): void
    {
        $this->mockTaskManager();

        Loader::deactivate();

        self::assertSame(['cancel'], self::$events);
    }
}
