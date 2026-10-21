<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Actions;

use Brain\Monkey\Functions;
use Cdek\Actions\CalculateDeliveryAction;
use Cdek\Actions\RecalculateShippingAction;
use Cdek\Model\Order;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use ReflectionProperty;

final class RecalculateShippingActionTest extends TestCase
{
    private array $originalPost;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalPost = $_POST;
        $_POST              = [];

        $this->setAddedError(false);

        Functions\when('sanitize_key')->returnArg();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('esc_html')->returnArg();
    }

    protected function tearDown(): void
    {
        $_POST = $this->originalPost;

        $this->setAddedError(false);

        parent::tearDown();
    }

    private function setAddedError(bool $value): void
    {
        $property = new ReflectionProperty(RecalculateShippingAction::class, 'addedError');
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }

    private function mockAdminAjaxRecalc(bool $isAjax = true, bool $isAdmin = true): void
    {
        $_POST['action'] = 'woocommerce_calc_line_taxes';

        Functions\when('is_ajax')->justReturn($isAjax);
        Functions\when('is_admin')->justReturn($isAdmin);
    }

    private function mockWcOrder(): MockInterface
    {
        $item = Mockery::mock();
        $item->shouldReceive('get_product')->andReturn('product');
        $item->shouldReceive('get_quantity')->andReturn(2);

        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_items')->andReturn([$item]);
        $order->shouldReceive('get_shipping_city')->andReturn('Moscow');
        $order->shouldReceive('get_shipping_country')->andReturn('RU');
        $order->shouldReceive('get_shipping_postcode')->andReturn('101000');

        return $order;
    }

    private function mockShipping(int $tariff, string $office = 'MSK1'): MockInterface
    {
        $shipping         = Mockery::mock();
        $shipping->tariff = $tariff;
        $shipping->office = $office;
        $shipping->shouldReceive('getMethod')->andReturn(Mockery::mock('alias:' . ShippingMethod::class));

        return $shipping;
    }

    private function mockOrderModelReturning(?MockInterface $shipping): void
    {
        Mockery::mock('overload:' . Order::class)
               ->shouldReceive('getShipping')
               ->andReturn($shipping);
    }

    private function mockCalculateDeliveryAction(array $rates): object
    {
        $stub = new class($rates) {
            public array $calls = [];

            public function __construct(private array $rates)
            {
            }

            public function __invoke(array $package, $method, bool $addTariffsToOffice): array
            {
                $this->calls[] = [$package, $method, $addTariffsToOffice];

                return $this->rates;
            }
        };

        Mockery::mock('alias:' . CalculateDeliveryAction::class)
               ->shouldReceive('new')
               ->andReturn($stub);

        return $stub;
    }

    public function testDoesNothingWhenActionIsNotPosted(): void
    {
        Functions\when('is_ajax')->justReturn(true);
        Functions\when('is_admin')->justReturn(true);

        (new RecalculateShippingAction())(false, Mockery::mock('WC_Order'));

        $this->addToAssertionCount(1);
    }

    public function testDoesNothingWhenPostedActionIsDifferent(): void
    {
        $_POST['action'] = 'woocommerce_save_order_items';

        Functions\when('is_ajax')->justReturn(true);
        Functions\when('is_admin')->justReturn(true);

        (new RecalculateShippingAction())(false, Mockery::mock('WC_Order'));

        $this->addToAssertionCount(1);
    }

    public function testDoesNothingWhenOrderIsNotWcOrder(): void
    {
        $this->mockAdminAjaxRecalc();

        (new RecalculateShippingAction())(false, Mockery::mock('WC_Abstract_Order'));

        $this->addToAssertionCount(1);
    }

    public function testDoesNothingWhenNotAjax(): void
    {
        $this->mockAdminAjaxRecalc(false, true);

        (new RecalculateShippingAction())(false, Mockery::mock('WC_Order'));

        $this->addToAssertionCount(1);
    }

    public function testDoesNothingWhenNotAdmin(): void
    {
        $this->mockAdminAjaxRecalc(true, false);

        (new RecalculateShippingAction())(false, Mockery::mock('WC_Order'));

        $this->addToAssertionCount(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDoesNothingWhenOrderHasNoCdekShipping(): void
    {
        $this->mockAdminAjaxRecalc();
        $this->mockOrderModelReturning(null);

        (new RecalculateShippingAction())(false, $this->mockWcOrder());

        $this->addToAssertionCount(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testUpdatesShippingWithRecalculatedTariff(): void
    {
        $this->mockAdminAjaxRecalc();

        $shipping = $this->mockShipping(136);
        $shipping->shouldReceive('updateTotal')->once()->with(250.5);
        $shipping->shouldReceive('updateName')->once()->with('CDEK: Door');
        $shipping->shouldReceive('save')->once();
        $this->mockOrderModelReturning($shipping);

        $calc = $this->mockCalculateDeliveryAction([
            136 => ['cost' => '250.5', 'label' => 'CDEK: Door', 'width' => 10, 'height' => 20, 'length' => 30],
            137 => ['cost' => '1', 'label' => 'other', 'width' => 1, 'height' => 1, 'length' => 1],
        ]);

        (new RecalculateShippingAction())(false, $this->mockWcOrder());

        self::assertCount(1, $calc->calls);
        [$package, $method, $addTariffsToOffice] = $calc->calls[0];
        self::assertSame([
            'contents'    => [['data' => 'product', 'quantity' => 2]],
            'destination' => ['city' => 'Moscow', 'country' => 'RU', 'postcode' => '101000'],
        ], $package);
        self::assertInstanceOf(ShippingMethod::class, $method);
        self::assertTrue($addTariffsToOffice);

        self::assertSame(10, $shipping->width);
        self::assertSame(20, $shipping->height);
        self::assertSame(30, $shipping->length);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testPrintsErrorWithAvailableTariffsWhenSelectedTariffIsMissing(): void
    {
        $this->mockAdminAjaxRecalc();

        $shipping = $this->mockShipping(999);
        $shipping->shouldReceive('save')->never();
        $shipping->shouldReceive('updateTotal')->never();
        $this->mockOrderModelReturning($shipping);

        $this->mockCalculateDeliveryAction([136 => ['cost' => '1'], 137 => ['cost' => '2']]);

        $this->expectOutputString(
            '<div class="cdek-error">The selected CDEK tariff is not available with the specified parameters. '
            . 'Available tariffs with codes: 136, 137</div>',
        );

        (new RecalculateShippingAction())(false, $this->mockWcOrder());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testPrintsMissingTariffErrorOnlyOncePerRequest(): void
    {
        $this->mockAdminAjaxRecalc();

        $shipping = $this->mockShipping(999);
        $this->mockOrderModelReturning($shipping);

        $this->mockCalculateDeliveryAction([136 => ['cost' => '1']]);

        $action = new RecalculateShippingAction();
        $order  = $this->mockWcOrder();

        ob_start();
        $action(false, $order);
        $action(false, $order);
        $output = ob_get_clean();

        self::assertSame(1, substr_count($output, 'cdek-error'));
    }
}
