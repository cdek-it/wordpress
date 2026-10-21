<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Helpers;

use Brain\Monkey\Functions;
use Cdek\Exceptions\CacheException;
use Cdek\Helpers\Cache;
use Cdek\Loader;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use ReflectionClass;

/**
 * `Cache` мокается `alias:` как коллаборатор в `TokensSyncCommandTest`, поэтому
 * юнит-тест РЕАЛЬНОГО класса должен гонять каждый метод в отдельном процессе
 * (см. `ShippingItemTest`/[[mockery-alias-vs-overload]]) - иначе при полном
 * прогоне сьюта `Cache` уже будет заменён Mockery-алиасом из другого файла.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class CacheTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDir = sys_get_temp_dir().'/cdek-cache-test-'.uniqid('', true);
        mkdir($this->cacheDir, 0777, true);

        define('WP_CONTENT_DIR', $this->cacheDir);
        define('FS_CHMOD_FILE', 0644);

        $reflection = (new ReflectionClass(Loader::class))->getProperty('pluginName');
        $reflection->setAccessible(true);
        $reflection->setValue(null, 'CDEK Delivery');

        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();
    }

    protected function tearDown(): void
    {
        global $wp_filesystem;
        $wp_filesystem = null;

        @unlink($this->cacheFilePath());
        @rmdir($this->cacheDir);

        parent::tearDown();
    }

    private function cacheFilePath(): string
    {
        return $this->cacheDir.DIRECTORY_SEPARATOR.'.cdekdelivery.php';
    }

    private function writeCacheFile(array $data): void
    {
        file_put_contents($this->cacheFilePath(), '<?php return '.var_export($data, true).';');
    }

    private function mockWritableFilesystem(bool $fileAlreadyExists): MockInterface
    {
        global $wp_filesystem;

        $wp_filesystem = Mockery::mock();
        $wp_filesystem->shouldReceive('exists')->with($this->cacheFilePath())->andReturn($fileAlreadyExists);

        if ($fileAlreadyExists) {
            $wp_filesystem->shouldReceive('is_writable')->with($this->cacheFilePath())->andReturn(true);
        } else {
            $wp_filesystem->shouldReceive('is_writable')->with(WP_CONTENT_DIR)->andReturn(true);
        }

        return $wp_filesystem;
    }

    public function testHasReturnsFalseWhenNoCacheFileExists(): void
    {
        self::assertFalse(Cache::has('foo'));
    }

    public function testHasReturnsTrueWhenKeyPresentInCacheFile(): void
    {
        $this->writeCacheFile(['foo' => 'bar']);

        self::assertTrue(Cache::has('foo'));
    }

    public function testHasReturnsFalseWhenKeyMissingFromCacheFile(): void
    {
        $this->writeCacheFile(['foo' => 'bar']);

        self::assertFalse(Cache::has('missing'));
    }

    public function testGetReturnsNullWhenNoCacheFileExists(): void
    {
        self::assertNull(Cache::get('foo'));
    }

    public function testGetReturnsStoredValueFromCacheFile(): void
    {
        $this->writeCacheFile(['foo' => 'bar', 'baz' => 42]);

        self::assertSame('bar', Cache::get('foo'));
        self::assertSame(42, Cache::get('baz'));
    }

    public function testGetReturnsNullForMissingKeyInCacheFile(): void
    {
        $this->writeCacheFile(['foo' => 'bar']);

        self::assertNull(Cache::get('missing'));
    }

    public function testGetDoesNotReReadCacheFileOnSubsequentCalls(): void
    {
        $this->writeCacheFile(['foo' => 'bar']);

        self::assertSame('bar', Cache::get('foo'));

        unlink($this->cacheFilePath());

        self::assertSame('bar', Cache::get('foo'));
    }

    public function testRememberReturnsCachedValueWithoutInvokingCallbackWhenKeyExists(): void
    {
        $this->writeCacheFile(['foo' => 'bar']);

        $invoked  = false;
        $callback = static function () use (&$invoked) {
            $invoked = true;

            return 'ignored';
        };

        $result = Cache::remember('foo', $callback);

        self::assertSame('bar', $result);
        self::assertFalse($invoked);
    }

    public function testRememberInvokesCallbackAndPersistsValueWhenKeyIsMissing(): void
    {
        $this->mockWritableFilesystem(false)->shouldReceive('put_contents');

        $result = Cache::remember('foo', static fn() => 'computed-value');

        self::assertSame('computed-value', $result);
        self::assertSame('computed-value', Cache::get('foo'));
    }

    public function testPutStoresSingleKeyValuePairAndPersistsToFilesystem(): void
    {
        $wpFilesystem = $this->mockWritableFilesystem(false);
        $wpFilesystem->shouldReceive('put_contents')
                      ->once()
                      ->with(
                          $this->cacheFilePath(),
                          '<?php defined("ABSPATH") or exit; return '.var_export(['foo' => 'bar'], true).';'.PHP_EOL,
                          FS_CHMOD_FILE,
                      );

        Cache::put('foo', 'bar');

        self::assertSame('bar', Cache::get('foo'));
    }

    public function testPutStoresMultipleKeyValuePairsFromArray(): void
    {
        $wpFilesystem = $this->mockWritableFilesystem(false);
        $wpFilesystem->shouldReceive('put_contents')->once();

        Cache::put(['a' => 1, 'b' => 2]);

        self::assertSame(1, Cache::get('a'));
        self::assertSame(2, Cache::get('b'));
    }

    public function testPutThrowsCacheExceptionWhenExistingCacheFileIsNotWritable(): void
    {
        $this->writeCacheFile([]);

        global $wp_filesystem;
        $wp_filesystem = Mockery::mock();
        $wp_filesystem->shouldReceive('exists')->with($this->cacheFilePath())->andReturn(true);
        $wp_filesystem->shouldReceive('is_writable')->with($this->cacheFilePath())->andReturn(false);
        $wp_filesystem->shouldNotReceive('put_contents');

        $this->expectException(CacheException::class);

        Cache::put('foo', 'bar');
    }

    public function testClearDeletesCacheFileAndResetsInMemoryStore(): void
    {
        $this->writeCacheFile(['foo' => 'bar']);

        self::assertSame('bar', Cache::get('foo'));

        Functions\expect('wp_delete_file')
                 ->once()
                 ->with($this->cacheFilePath())
                 ->andReturnUsing(function (string $path) {
                     unlink($path);
                 });

        Cache::clear();

        self::assertFileDoesNotExist($this->cacheFilePath());
        self::assertNull(Cache::get('foo'));
    }
}
