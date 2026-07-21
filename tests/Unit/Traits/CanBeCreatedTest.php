<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Traits;

use Cdek\Tests\TestCase;
use Cdek\Traits\CanBeCreated;

final class CanBeCreatedDouble
{
    use CanBeCreated;

    public array $args;

    public function __construct(...$args)
    {
        $this->args = $args;
    }
}

final class CanBeCreatedTest extends TestCase
{
    public function testNewReturnsInstanceOfUsingClass(): void
    {
        $instance = CanBeCreatedDouble::new();

        self::assertInstanceOf(CanBeCreatedDouble::class, $instance);
    }

    public function testNewForwardsAllArgumentsToConstructor(): void
    {
        $instance = CanBeCreatedDouble::new('foo', 42, ['bar' => 'baz']);

        self::assertSame(['foo', 42, ['bar' => 'baz']], $instance->args);
    }

    public function testNewReturnsNewInstanceOnEachCall(): void
    {
        $first  = CanBeCreatedDouble::new();
        $second = CanBeCreatedDouble::new();

        self::assertNotSame($first, $second);
    }
}
