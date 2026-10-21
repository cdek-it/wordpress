<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Model;

use Cdek\Model\Log;
use Cdek\Tests\TestCase;
use Exception;

final class LogTest extends TestCase
{
    public function testGetMessageReturnsConstructorValue(): void
    {
        $log = Log::initWithContext('some message', []);

        self::assertSame('some message', $log->getMessage());
    }

    public function testGetLogReturnsContextWhenInitializedWithContext(): void
    {
        $log = Log::initWithContext('some message', ['foo' => 'bar']);

        self::assertSame(['foo' => 'bar'], $log->getLog());
    }

    public function testGetLogReturnsExceptionDataWhenInitializedWithException(): void
    {
        $exception = new Exception('exception message');
        $line      = $exception->getLine();
        $file      = $exception->getFile();

        $log = Log::initWithException('some message', $exception);

        self::assertSame([
            'message' => 'exception message',
            'file'    => "{$file}:{$line}",
            'trace'   => $exception->getTrace(),
        ], $log->getLog());
    }

    public function testInitLogDelegatesToInitWithContextForArray(): void
    {
        $log = Log::initLog('some message', ['foo' => 'bar']);

        self::assertSame(['foo' => 'bar'], $log->getLog());
    }

    public function testInitLogDelegatesToInitWithExceptionForThrowable(): void
    {
        $exception = new Exception('exception message');

        $log = Log::initLog('some message', $exception);

        self::assertSame('exception message', $log->getLog()['message']);
    }
}
