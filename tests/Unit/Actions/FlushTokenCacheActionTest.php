<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Actions;

use Cdek\Actions\FlushTokenCacheAction;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;

final class FlushTokenCacheActionTest extends TestCase
{
    private function mockShippingMethod(): MockInterface
    {
        $shippingMethod = Mockery::mock('alias:' . ShippingMethod::class);
        $shippingMethod->shouldReceive('factory')->andReturn($shippingMethod);

        return $shippingMethod;
    }

    public function testInvokeFlushesTokenCacheByClearingTokenOption(): void
    {
        $shippingMethod = $this->mockShippingMethod();
        $shippingMethod->shouldReceive('update_option')->once()->with('token', null);

        (new FlushTokenCacheAction())();

        self::assertTrue(true);
    }

    public function testNewCreatesActionThatFlushesTokenCache(): void
    {
        $shippingMethod = $this->mockShippingMethod();
        $shippingMethod->shouldReceive('update_option')->once()->with('token', null);

        $action = FlushTokenCacheAction::new();

        self::assertInstanceOf(FlushTokenCacheAction::class, $action);

        $action();
    }

    public function testInvokeFlushesTokenCacheOnEveryCallWithoutCaching(): void
    {
        $shippingMethod = $this->mockShippingMethod();
        $shippingMethod->shouldReceive('update_option')->twice()->with('token', null);

        $action = new FlushTokenCacheAction();
        $action();
        $action();

        self::assertTrue(true);
    }
}
