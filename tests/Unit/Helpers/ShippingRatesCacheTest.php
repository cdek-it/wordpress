<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Helpers;

use Brain\Monkey\Functions;
use Cdek\Helpers\ShippingRatesCache;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;

class ShippingRatesCacheTest extends TestCase
{
    private function mockWcSession(?MockInterface $session): void
    {
        $wc          = Mockery::mock();
        $wc->session = $session;

        Functions\when('WC')->justReturn($wc);
    }

    public function testGetReturnsNullWhenSessionIsNull(): void
    {
        $this->mockWcSession(null);

        self::assertNull(ShippingRatesCache::get(1, ['city' => 'Москва'], []));
    }

    public function testGetReturnsNullWhenCacheIsEmpty(): void
    {
        $session = Mockery::mock();
        $session->shouldReceive('get')
                ->once()
                ->andReturn(null);

        $this->mockWcSession($session);

        self::assertNull(ShippingRatesCache::get(1, ['city' => 'Москва'], []));
    }

    public function testGetReturnsNullWhenKeyDiffers(): void
    {
        $captured = $this->captureKeyFromSet(1, ['city' => 'Москва'], []);

        $session = Mockery::mock();
        $session->shouldReceive('get')
                ->once()
                ->andReturn([
                    'key'     => 'not-the-same-key',
                    'expires' => time() + 100,
                    'rates'   => ['rate'],
                ]);

        $this->mockWcSession($session);

        self::assertNull(ShippingRatesCache::get(1, ['city' => 'Москва'], []));
    }

    public function testGetReturnsNullWhenExpired(): void
    {
        $key = $this->captureKeyFromSet(1, ['city' => 'Москва'], []);

        $session = Mockery::mock();
        $session->shouldReceive('get')
                ->once()
                ->andReturn([
                    'key'     => $key,
                    'expires' => time() - 1,
                    'rates'   => ['rate'],
                ]);

        $this->mockWcSession($session);

        self::assertNull(ShippingRatesCache::get(1, ['city' => 'Москва'], []));
    }

    public function testGetReturnsCachedRatesOnHit(): void
    {
        $key = $this->captureKeyFromSet(1, ['city' => 'Москва'], []);

        $session = Mockery::mock();
        $session->shouldReceive('get')
                ->once()
                ->andReturn([
                    'key'     => $key,
                    'expires' => time() + 100,
                    'rates'   => ['rate-1', 'rate-2'],
                ]);

        $this->mockWcSession($session);

        self::assertSame(['rate-1', 'rate-2'], ShippingRatesCache::get(1, ['city' => 'Москва'], []));
    }

    public function testKeyDiffersByDestination(): void
    {
        $keyMoscow = $this->captureKeyFromSet(1, ['city' => 'Москва'], []);
        $keySpb    = $this->captureKeyFromSet(1, ['city' => 'Санкт-Петербург'], []);

        self::assertNotSame($keyMoscow, $keySpb);
    }

    public function testKeyDiffersByInstanceId(): void
    {
        $keyOne = $this->captureKeyFromSet(1, ['city' => 'Москва'], []);
        $keyTwo = $this->captureKeyFromSet(2, ['city' => 'Москва'], []);

        self::assertNotSame($keyOne, $keyTwo);
    }

    public function testKeyDiffersByCartSnapshot(): void
    {
        $keyLight = $this->captureKeyFromSet(1, ['city' => 'Москва'], ['items' => [['weight' => 1]]]);
        $keyHeavy = $this->captureKeyFromSet(1, ['city' => 'Москва'], ['items' => [['weight' => 10]]]);

        self::assertNotSame($keyLight, $keyHeavy);
    }

    public function testSetDoesNothingWhenSessionIsNull(): void
    {
        $this->mockWcSession(null);

        ShippingRatesCache::set(1, ['city' => 'Москва'], [], ['rate']);

        $this->addToAssertionCount(1);
    }

    public function testSetStoresFutureExpiry(): void
    {
        $captured = null;

        $session = Mockery::mock();
        $session->shouldReceive('set')
                ->once()
                ->with(Mockery::any(), Mockery::on(static function ($value) use (&$captured) {
                    $captured = $value;

                    return true;
                }));

        $this->mockWcSession($session);

        ShippingRatesCache::set(1, ['city' => 'Москва'], [], ['rate']);

        self::assertGreaterThan(time(), $captured['expires']);
        self::assertSame(['rate'], $captured['rates']);
    }

    private function captureKeyFromSet(int $instanceId, array $destination, array $cartSnapshot): string
    {
        $captured = null;

        $session = Mockery::mock();
        $session->shouldReceive('set')
                ->once()
                ->with(Mockery::any(), Mockery::on(static function ($value) use (&$captured) {
                    $captured = $value;

                    return true;
                }));

        $this->mockWcSession($session);

        ShippingRatesCache::set($instanceId, $destination, $cartSnapshot, []);

        return $captured['key'];
    }
}
