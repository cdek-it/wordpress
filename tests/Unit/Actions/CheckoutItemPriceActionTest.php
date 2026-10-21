<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Actions;

use Brain\Monkey\Functions;
use Cdek\Actions\CheckoutItemPriceAction;
use Cdek\Helpers\CheckoutHelper;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use ReflectionProperty;

final class CheckoutItemPriceActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // `self::$mutex` - статическое свойство самого экшена, переживает между
        // тестами в рамках одного процесса PHPUnit - сбрасываем перед каждым тестом,
        // иначе один тест может "заразить" следующий уже выставленным mutex=true.
        $this->setMutex(false);
    }

    protected function tearDown(): void
    {
        $this->setMutex(false);
        parent::tearDown();
    }

    private function setMutex(bool $value): void
    {
        $property = new ReflectionProperty(CheckoutItemPriceAction::class, 'mutex');
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }

    /**
     * `getSelectedShippingRate()` и `ShippingMethod::factory()` - реальные статические
     * методы, замоканные через `alias:`, аналогично паттерну в OrderCreateActionTest.
     */
    private function mockSelectedRate(?MockInterface $rate): void
    {
        Mockery::mock('alias:' . CheckoutHelper::class)
            ->shouldReceive('getSelectedShippingRate')
            ->andReturn($rate);
    }

    private function mockSession(?string $paymentMethod): void
    {
        $session = null;

        if ($paymentMethod !== null) {
            $session = Mockery::mock();
            $session->shouldReceive('get')->with('chosen_payment_method')->andReturn($paymentMethod);
        }

        $wc          = Mockery::mock();
        $wc->session = $session;

        Functions\when('WC')->justReturn($wc);
    }

    private function mockShippingMethod(int $instanceId, $percentCod): MockInterface
    {
        $method             = Mockery::mock('alias:' . ShippingMethod::class);
        $method->percentcod = $percentCod;
        $method->shouldReceive('factory')->with($instanceId)->andReturn($method);

        return $method;
    }

    private function mockRate(int $instanceId): MockInterface
    {
        $rate = Mockery::mock('WC_Shipping_Rate');
        $rate->shouldReceive('get_instance_id')->andReturn($instanceId);

        return $rate;
    }

    private function mockProduct(string $price): MockInterface
    {
        $product = Mockery::mock('WC_Product');
        $product->shouldReceive('get_price')->andReturn($price);

        return $product;
    }

    public function testInvokeDoesNothingWhenNoShippingRateIsSelected(): void
    {
        $this->mockSelectedRate(null);

        // Ни `WC()`, ни `$cart->get_cart()` не должны понадобиться - если экшен
        // всё же дойдёт до них, немокнутый вызов упадёт с BadMethodCallException.
        $cart = Mockery::mock('WC_Cart');

        (new CheckoutItemPriceAction())($cart);

        self::assertFalse((new ReflectionProperty(CheckoutItemPriceAction::class, 'mutex'))->getValue());
    }

    public function testInvokeDoesNothingWhenSessionIsNull(): void
    {
        $this->mockSelectedRate($this->mockRate(5));
        $this->mockSession(null);

        $cart = Mockery::mock('WC_Cart');

        (new CheckoutItemPriceAction())($cart);

        self::assertFalse((new ReflectionProperty(CheckoutItemPriceAction::class, 'mutex'))->getValue());
    }

    public function testInvokeDoesNothingWhenPaymentMethodIsNotCod(): void
    {
        $this->mockSelectedRate($this->mockRate(5));
        $this->mockSession('bacs');

        $cart = Mockery::mock('WC_Cart');

        (new CheckoutItemPriceAction())($cart);

        self::assertFalse((new ReflectionProperty(CheckoutItemPriceAction::class, 'mutex'))->getValue());
    }

    /**
     * @dataProvider emptyPercentCodProvider
     */
    public function testInvokeDoesNothingWhenPercentCodIsEmpty($percentCod): void
    {
        $this->mockSelectedRate($this->mockRate(5));
        $this->mockSession('cod');
        $this->mockShippingMethod(5, $percentCod);

        $cart = Mockery::mock('WC_Cart');

        (new CheckoutItemPriceAction())($cart);

        self::assertFalse((new ReflectionProperty(CheckoutItemPriceAction::class, 'mutex'))->getValue());
    }

    public static function emptyPercentCodProvider(): array
    {
        return [
            'empty string' => [''],
            'zero string'  => ['0'],
            'zero int'     => [0],
            'null'         => [null],
        ];
    }

    public function testInvokeAppliesPercentCodToEachCartItemPrice(): void
    {
        $this->mockSelectedRate($this->mockRate(5));
        $this->mockSession('cod');
        $this->mockShippingMethod(5, '70');

        $productA = $this->mockProduct('100');
        $productA->shouldReceive('set_price')->once()->with(70.0);

        $productB = $this->mockProduct('50');
        $productB->shouldReceive('set_price')->once()->with(35.0);

        $cart = Mockery::mock('WC_Cart');
        $cart->shouldReceive('get_cart')->once()->andReturn([
            ['data' => $productA],
            ['data' => $productB],
        ]);

        (new CheckoutItemPriceAction())($cart);

        self::assertTrue((new ReflectionProperty(CheckoutItemPriceAction::class, 'mutex'))->getValue());
    }

    public function testInvokeMutexPreventsReentrantPriceAdjustmentWithinSameRequest(): void
    {
        $this->mockSelectedRate($this->mockRate(5));
        $this->mockSession('cod');
        $this->mockShippingMethod(5, '50');

        $product = $this->mockProduct('100');

        $setPriceCalls = 0;
        $product->shouldReceive('set_price')->andReturnUsing(static function () use (&$setPriceCalls) {
            $setPriceCalls++;
        });

        // `get_cart()` замокан без ->once(), чтобы не дублировать проверку mutex -
        // сам факт того, что set_price() не вызвался повторно, уже доказывает,
        // что второй вызов экшена не дошёл до цикла по корзине.
        $cart = Mockery::mock('WC_Cart');
        $cart->shouldReceive('get_cart')->andReturn([['data' => $product]]);

        $action = new CheckoutItemPriceAction();
        $action($cart);
        $action($cart);

        self::assertSame(1, $setPriceCalls);
    }
}
