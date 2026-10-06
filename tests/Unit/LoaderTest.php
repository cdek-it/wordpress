<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit;

use Brain\Monkey\Functions;
use Cdek\Config;
use Cdek\Loader;
use Cdek\Tests\TestCase;
use ReflectionClass;
use stdClass;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LoaderTest extends TestCase
{
    private const MAIN_FILE_PATH = '/var/www/html/wp-content/plugins/cdekdelivery/cdek.php';
    private const MAIN_FILE      = 'cdekdelivery/cdek.php';
    private const PLUGIN_NAME    = 'CDEK Delivery';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setLoaderStatic('pluginMainFilePath', self::MAIN_FILE_PATH);
        $this->setLoaderStatic('pluginMainFile', self::MAIN_FILE);
        $this->setLoaderStatic('pluginName', self::PLUGIN_NAME);
        $this->setLoaderStatic('pluginVersion', '1.2.3');
        $this->setLoaderStatic('debug', false);
    }

    /**
     * @param mixed $value
     * @throws \ReflectionException
     */
    private function setLoaderStatic(string $property, $value): void
    {
        $reflection = (new ReflectionClass(Loader::class))->getProperty($property);
        $reflection->setAccessible(true);
        $reflection->setValue(null, $value);
    }

    // ---- простые геттеры ----

    /**
     * @dataProvider debugProvider
     */
    public function testDebugReturnsCurrentFlag(bool $flag): void
    {
        $this->setLoaderStatic('debug', $flag);

        self::assertSame($flag, Loader::debug());
    }

    public static function debugProvider(): array
    {
        return ['enabled' => [true], 'disabled' => [false]];
    }

    /**
     * @dataProvider versionProvider
     */
    public function testGetPluginVersionReturnsCurrentVersion(string $version): void
    {
        $this->setLoaderStatic('pluginVersion', $version);

        self::assertSame($version, Loader::getPluginVersion());
    }

    public static function versionProvider(): array
    {
        return [
            'release'    => ['5.1.1'],
            'prerelease' => ['5.2.0-rc.1'],
            'empty'      => [''],
        ];
    }

    public function testGetPluginNameReturnsCurrentName(): void
    {
        self::assertSame(self::PLUGIN_NAME, Loader::getPluginName());

        $this->setLoaderStatic('pluginName', 'Другое имя');

        self::assertSame('Другое имя', Loader::getPluginName());
    }

    public function testGetPluginFileReturnsPluginBasename(): void
    {
        self::assertSame(self::MAIN_FILE, Loader::getPluginFile());
    }

    // ---- пути и URL ----

    public function testGetPluginUrlWithoutPathReturnsPluginDirectoryUrl(): void
    {
        Functions\expect('plugin_dir_url')
            ->with(self::MAIN_FILE_PATH)
            ->andReturn('https://shop.test/wp-content/plugins/cdekdelivery/');

        self::assertSame('https://shop.test/wp-content/plugins/cdekdelivery/', Loader::getPluginUrl());
    }

    /**
     * @dataProvider urlPathProvider
     */
    public function testGetPluginUrlAppendsPathWithoutLeadingSlash(string $path, string $expected): void
    {
        Functions\when('plugin_dir_url')->justReturn('https://shop.test/wp-content/plugins/cdekdelivery/');

        self::assertSame($expected, Loader::getPluginUrl($path));
    }

    public static function urlPathProvider(): array
    {
        $base = 'https://shop.test/wp-content/plugins/cdekdelivery/';

        return [
            'relative path'      => ['build/map.js', $base . 'build/map.js'],
            'leading slash'      => ['/build/map.js', $base . 'build/map.js'],
            'several slashes'    => ['///build/map.js', $base . 'build/map.js'],
            'empty string'       => ['', $base],
        ];
    }

    public function testGetPluginPathWithoutPathReturnsPluginDirectory(): void
    {
        Functions\expect('plugin_dir_path')
            ->with(self::MAIN_FILE_PATH)
            ->andReturn('/var/www/html/wp-content/plugins/cdekdelivery/');

        self::assertSame('/var/www/html/wp-content/plugins/cdekdelivery/', Loader::getPluginPath());
    }

    public function testGetPluginPathTrimsLeadingDirectorySeparator(): void
    {
        Functions\when('plugin_dir_path')->justReturn('/var/www/html/wp-content/plugins/cdekdelivery/');

        self::assertSame(
            '/var/www/html/wp-content/plugins/cdekdelivery/src/Config.php',
            Loader::getPluginPath('/src/Config.php'),
        );
        self::assertSame(
            '/var/www/html/wp-content/plugins/cdekdelivery/src/Config.php',
            Loader::getPluginPath('src/Config.php'),
        );
    }

    public function testGetTemplateBuildsPathToTemplateFile(): void
    {
        Functions\when('plugin_dir_path')->justReturn('/var/www/html/wp-content/plugins/cdekdelivery/');

        self::assertSame(
            '/var/www/html/wp-content/plugins/cdekdelivery/templates/admin-order.php',
            Loader::getTemplate('admin-order'),
        );
    }

    // ---- scheduleUpgrade ----

    private function expectUpgradeScheduled(): void
    {
        Functions\expect('as_schedule_single_action')
            ->once()
            ->withArgs(
                static function ($timestamp, $hook, $args, $group, $unique): bool {
                    return $timestamp >= time() + 59 && $timestamp <= time() + 61
                           && $hook === Config::UPGRADE_HOOK_NAME
                           && $args === []
                           && $group === 'cdekdelivery'
                           && $unique === true;
                },
            );
    }

    private function expectNoUpgradeScheduled(): void
    {
        Functions\expect('as_schedule_single_action')->never();
    }

    private function upgraderWithPluginName(string $name): stdClass
    {
        $wp                  = new stdClass();
        $wp->new_plugin_data = ['Name' => $name];

        return $wp;
    }

    public function testScheduleUpgradeIgnoresNonPluginUpgrades(): void
    {
        $this->expectNoUpgradeScheduled();

        Loader::scheduleUpgrade(
            $this->upgraderWithPluginName(self::PLUGIN_NAME),
            ['type' => 'theme', 'action' => 'install', 'plugins' => [self::MAIN_FILE]],
        );

        $this->addToAssertionCount(1);
    }

    public function testScheduleUpgradeSchedulesAfterInstallOfThisPlugin(): void
    {
        $this->expectUpgradeScheduled();

        Loader::scheduleUpgrade(
            $this->upgraderWithPluginName(self::PLUGIN_NAME),
            ['type' => 'plugin', 'action' => 'install'],
        );

        $this->addToAssertionCount(1);
    }

    public function testScheduleUpgradeIgnoresInstallOfAnotherPlugin(): void
    {
        $this->expectNoUpgradeScheduled();

        Loader::scheduleUpgrade(
            $this->upgraderWithPluginName('Some Other Plugin'),
            ['type' => 'plugin', 'action' => 'install'],
        );

        $this->addToAssertionCount(1);
    }

    public function testScheduleUpgradeSchedulesWhenThisPluginIsUpdated(): void
    {
        $this->expectUpgradeScheduled();

        Loader::scheduleUpgrade(
            new stdClass(),
            ['type' => 'plugin', 'action' => 'update', 'plugins' => ['akismet/akismet.php', self::MAIN_FILE]],
        );

        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider foreignUpdateProvider
     */
    public function testScheduleUpgradeIgnoresUpdateOfOtherPlugins(array $options): void
    {
        $this->expectNoUpgradeScheduled();

        Loader::scheduleUpgrade(new stdClass(), $options);

        $this->addToAssertionCount(1);
    }

    public static function foreignUpdateProvider(): array
    {
        return [
            'other plugin'       => [['type' => 'plugin', 'action' => 'update', 'plugins' => ['akismet/akismet.php']]],
            'empty plugin list'  => [['type' => 'plugin', 'action' => 'update', 'plugins' => []]],
            'no plugin list'     => [['type' => 'plugin', 'action' => 'update']],
        ];
    }
}
