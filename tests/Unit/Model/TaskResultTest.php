<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Model;

use Cdek\Model\TaskResult;
use Cdek\Tests\TestCase;

final class TaskResultTest extends TestCase
{
    public function testGetStatusReturnsConstructorValue(): void
    {
        $result = new TaskResult('completed');

        self::assertSame('completed', $result->getStatus());
    }

    public function testGetDataReturnsNullByDefault(): void
    {
        $result = new TaskResult('completed');

        self::assertNull($result->getData());
    }

    public function testGetDataReturnsConstructorValue(): void
    {
        $result = new TaskResult('completed', ['uuid' => '123']);

        self::assertSame(['uuid' => '123'], $result->getData());
    }

    public function testGetCurrentPageReturnsNullByDefault(): void
    {
        $result = new TaskResult('completed');

        self::assertNull($result->getCurrentPage());
    }

    public function testGetCurrentPageReturnsConstructorValue(): void
    {
        $result = new TaskResult('completed', null, 2);

        self::assertSame(2, $result->getCurrentPage());
    }

    public function testGetTotalPagesReturnsNullByDefault(): void
    {
        $result = new TaskResult('completed');

        self::assertNull($result->getTotalPages());
    }

    public function testGetTotalPagesReturnsConstructorValue(): void
    {
        $result = new TaskResult('completed', null, null, 5);

        self::assertSame(5, $result->getTotalPages());
    }

    public function testAllGettersReturnValuesWhenFullyConstructed(): void
    {
        $result = new TaskResult('in_progress', ['foo' => 'bar'], 3, 10);

        self::assertSame('in_progress', $result->getStatus());
        self::assertSame(['foo' => 'bar'], $result->getData());
        self::assertSame(3, $result->getCurrentPage());
        self::assertSame(10, $result->getTotalPages());
    }
}
