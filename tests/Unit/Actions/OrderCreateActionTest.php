<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Actions;

use Brain\Monkey\Functions;
use Cdek\Actions\OrderCreateAction;
use Cdek\CdekApi;
use Cdek\CoreApi;
use Cdek\Exceptions\ShippingNotFoundException;
use Cdek\Model\Order;
use Cdek\Model\ShippingItem;
use Cdek\Loader;
use Cdek\Model\ValidationResult;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Mockery;
use ReflectionMethod;
use ReflectionProperty;

final class OrderCreateActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Exception-конструкторы (через ExceptionContract) читают Loader::$pluginName,
        // которое в реальном плагине инициализируется при загрузке - в тестах его
        // никто не выставляет, поэтому делаем это вручную рефлексией.
        $pluginName = new ReflectionProperty(Loader::class, 'pluginName');
        $pluginName->setAccessible(true);
        $pluginName->setValue(null, 'cdekdelivery');

        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('wp_rand')->alias(static fn($min = 0, $max = 2147483647) => mt_rand($min, $max));
        Functions\when('__')->returnArg();
    }

    /**
     * @return mixed
     */
    private function invokePrivate(object $object, string $method, array $args = [])
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    /**
     * @param  mixed  $value
     */
    private function setPrivateProperty(object $object, string $property, $value): void
    {
        $reflection = new ReflectionProperty($object, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }

    /**
     * @dataProvider stringToFloatProvider
     */
    public function testConvertStringToFloat(string $input, float $expected): void
    {
        $action = new OrderCreateAction();

        self::assertSame($expected, $this->invokePrivate($action, 'convertStringToFloat', [$input]));
    }

    public static function stringToFloatProvider(): array
    {
        return [
            'plain integer'                           => ['123', 123.0],
            'decimal with dot'                        => ['12.5', 12.5],
            // is_numeric() отсекает строку раньше, чем сработает str_replace(',', '.')
            // строки с запятой/пробелом никогда не считаются числовыми, всегда 0.0
            'comma-formatted number is not numeric'   => ['12,5', 0.0],
            'space-thousands number is not numeric'   => ['1 234,56', 0.0],
            'empty string'                            => ['', 0.0],
            'non numeric string'                      => ['abc', 0.0],
            'numeric string with surrounding spaces'  => [' 42 ', 42.0],
        ];
    }

    public function testConvertCurrencyToRubReturnsCostUnchangedWhenRubRateMissing(): void
    {
        global $woocommerce_wpml;

        $multiCurrency = Mockery::mock();
        $multiCurrency->shouldReceive('get_exchange_rates')->andReturn(['USD' => 1]);

        $woocommerce_wpml = Mockery::mock();
        $woocommerce_wpml->shouldReceive('get_multi_currency')->andReturn($multiCurrency);

        $action = new OrderCreateAction();

        self::assertSame(100.0, $this->invokePrivate($action, 'convertCurrencyToRub', [100.0, 'USD']));

        $woocommerce_wpml = null;
    }

    public function testConvertCurrencyToRubConvertsFromDefaultCurrency(): void
    {
        global $woocommerce_wpml;

        $multiCurrency = Mockery::mock();
        $multiCurrency->shouldReceive('get_exchange_rates')->andReturn(['USD' => 1, 'RUB' => 90.0]);

        $woocommerce_wpml = Mockery::mock();
        $woocommerce_wpml->shouldReceive('get_multi_currency')->andReturn($multiCurrency);

        $action = new OrderCreateAction();

        self::assertSame(900.0, $this->invokePrivate($action, 'convertCurrencyToRub', [10.0, 'USD']));

        $woocommerce_wpml = null;
    }

    public function testConvertCurrencyToRubConvertsFromNonDefaultCurrency(): void
    {
        global $woocommerce_wpml;

        $multiCurrency = Mockery::mock();
        $multiCurrency->shouldReceive('get_exchange_rates')->andReturn(['USD' => 1, 'EUR' => 0.9, 'RUB' => 90.0]);

        $woocommerce_wpml = Mockery::mock();
        $woocommerce_wpml->shouldReceive('get_multi_currency')->andReturn($multiCurrency);

        $action = new OrderCreateAction();

        self::assertSame(999.9, $this->invokePrivate($action, 'convertCurrencyToRub', [10.0, 'EUR']));

        $woocommerce_wpml = null;
    }

    public function testInvokeThrowsWhenOrderHasNoShipping(): void
    {
        // `__invoke()` первой же строкой делает `new CdekApi`, чей конструктор тянет
        // реальный ShippingMethod (extends WC_Shipping_Method) - глушим его, поведение
        // CdekApi в этих двух ветках не используется.
        Mockery::mock('overload:' . CdekApi::class);

        $order = Mockery::mock('overload:' . Order::class);
        $order->shouldReceive('getShipping')->once()->andReturn(null);

        $this->expectException(ShippingNotFoundException::class);

        (new OrderCreateAction())(123);
    }

    public function testInvokeReturnsSuccessWithoutCreatingNewOrderWhenTrackAlreadyExists(): void
    {
        Mockery::mock('overload:' . CdekApi::class);

        // shipping->tariff непустой, поэтому $this->order->tariff_id (единственное
        // "магическое" свойство Order, читаемое до входа в try) в этой ветке не читается.
        $shipping = Mockery::mock('alias:' . ShippingItem::class);
        $shipping->tariff = '139';

        $order = Mockery::mock('overload:' . Order::class);
        $order->shouldReceive('getShipping')->once()->andReturn($shipping);
        // Запись в "магическое" свойство Order безопасна (создаёт динамическое свойство
        // на конкретном инстансе), а вот последующее чтение с него же - нет, поэтому
        // здесь проверяем только сам факт сохранения, а не значение.
        $order->shouldReceive('save')->once();

        $coreApi = Mockery::mock('overload:' . CoreApi::class);
        $coreApi->shouldReceive('orderGet')->once()->with(123)->andReturn(['track' => 'EXISTING-TRACK']);

        $result = (new OrderCreateAction())(123);

        self::assertInstanceOf(ValidationResult::class, $result);
        self::assertTrue($result->state());
    }

    /**
     * buildRequestData()/buildPackagesData() читают $this->order/$this->shipping/$this->tariff,
     * которые обычно выставляет только сам __invoke(). Собираем OrderCreateAction напрямую
     * через рефлексию, минуя __invoke(), и подсовываем ему уже готовый, полностью
     * управляемый мок вместо того, который создал бы `new Order()`.
     *
     * `Order` мокается через `overload:` (не `alias:`) той же командой, что и в тестах
     * __invoke() ниже - смешивание alias/overload-режимов для одного и того же класса
     * в разных тестах одного файла ломается при смене порядка запуска тестов (проверено
     * эмпирически: `--order-by=random` даёт "Method ... does not exist on this mock object").
     *
     * @return array{0: OrderCreateAction, 1: object, 2: object, 3: object}
     */
    private function buildActionWithMocks(int $tariff): array
    {
        $order                 = Mockery::mock('overload:' . Order::class);
        $order->country        = 'RU';
        $order->phone          = '+79991234567';
        $order->id             = 555;
        $order->first_name     = 'Ivan';
        $order->last_name      = 'Ivanov';
        $order->billing_email  = 'ivan@example.com';
        $order->city           = 'Moscow';
        $order->postcode       = '101000';
        $order->address_1      = 'Tverskaya 1';
        $order->pvz_code       = '';
        $order->shipping_total = 0.0;
        $order->shipping_tax   = 0.0;

        $shippingMethod                                      = Mockery::mock('alias:' . ShippingMethod::class);
        $shippingMethod->shipper_name                        = 'Shipper';
        $shippingMethod->shipper_address                     = 'Shipper address';
        $shippingMethod->passport_series                     = '';
        $shippingMethod->passport_number                     = '';
        $shippingMethod->passport_date_of_issue              = '';
        $shippingMethod->passport_organization               = '';
        $shippingMethod->passport_date_of_birth              = '';
        $shippingMethod->tin                                 = '';
        $shippingMethod->seller_name                         = 'Seller';
        $shippingMethod->seller_company                      = 'Seller LLC';
        $shippingMethod->seller_email                        = 'seller@example.com';
        $shippingMethod->seller_phone                        = '+70000000000';
        $shippingMethod->seller_address                      = 'Seller address';
        $shippingMethod->city_code                           = '44';
        $shippingMethod->address                             = 'From address';
        $shippingMethod->international_mode                  = false;
        $shippingMethod->services_ban_attachment_inspection  = false;
        $shippingMethod->services_trying_on                  = false;
        $shippingMethod->services_part_deliv                 = false;

        $shippingItem         = Mockery::mock('alias:' . ShippingItem::class);
        $shippingItem->office = null;
        $shippingItem->tariff = (string)$tariff;
        $shippingItem->shouldReceive('getMethod')->andReturn($shippingMethod);

        Mockery::mock('overload:' . CoreApi::class)
            ->shouldReceive('validatePhone')
            ->andReturn('+79991234567');

        $action = new OrderCreateAction();
        $this->setPrivateProperty($action, 'order', $order);
        $this->setPrivateProperty($action, 'shipping', $shippingItem);
        $this->setPrivateProperty($action, 'tariff', $tariff);

        return [$action, $order, $shippingItem, $shippingMethod];
    }

    public function testBuildRequestDataUsesToLocationForDoorToDoorTariff(): void
    {
        [$action] = $this->buildActionWithMocks(139);

        $param = $this->invokePrivate($action, 'buildRequestData');

        self::assertSame(
            [
                'city'         => 'Moscow',
                'postal_code'  => '101000',
                'country_code' => 'RU',
                'address'      => 'Tverskaya 1',
            ],
            $param['to_location'],
        );
        self::assertArrayNotHasKey('delivery_point', $param);
    }

    public function testBuildRequestDataUsesDeliveryPointForToOfficeTariff(): void
    {
        [$action, , $shippingItem] = $this->buildActionWithMocks(138);
        $shippingItem->office = 'MSK123';

        $param = $this->invokePrivate($action, 'buildRequestData');

        self::assertSame('MSK123', $param['delivery_point']);
        self::assertArrayNotHasKey('to_location', $param);
    }

    public function testBuildRequestDataMergesInternationalFieldsWhenInternationalModeEnabled(): void
    {
        [$action, $order, , $shippingMethod] = $this->buildActionWithMocks(139);
        $shippingMethod->international_mode = true;
        $order->shouldReceive('meta')->andReturn('meta-value');

        $param = $this->invokePrivate($action, 'buildRequestData');

        self::assertSame('meta-value', $param['recipient']['tin']);
        self::assertSame('meta-value', $param['recipient']['passport_series']);
    }

    public function testBuildRequestDataIncludesServicesWhenApplicable(): void
    {
        [$action, , , $shippingMethod] = $this->buildActionWithMocks(139);
        $shippingMethod->services_trying_on = true;

        $param = $this->invokePrivate($action, 'buildRequestData');

        self::assertSame([['code' => 'TRYING_ON']], $param['services']);
    }

    public function testBuildRequestDataOmitsServicesWhenNoneApplicable(): void
    {
        [$action] = $this->buildActionWithMocks(139);

        $param = $this->invokePrivate($action, 'buildRequestData');

        self::assertArrayNotHasKey('services', $param);
    }

    public function testBuildRequestDataIncludesDeliveryRecipientCostWhenShouldBePaidUponDelivery(): void
    {
        [$action, $order] = $this->buildActionWithMocks(139);
        $order->shipping_total = 100.0;
        $order->shipping_tax   = 10.0;
        $order->shouldReceive('shouldBePaidUponDelivery')->andReturn(true);

        $param = $this->invokePrivate($action, 'buildRequestData');

        self::assertSame(110.0, $param['delivery_recipient_cost']['value']);
    }

    public function testBuildRequestDataOmitsDeliveryRecipientCostWhenShippingTotalIsZero(): void
    {
        [$action] = $this->buildActionWithMocks(139);

        $param = $this->invokePrivate($action, 'buildRequestData');

        self::assertArrayNotHasKey('delivery_recipient_cost', $param);
    }

    public function testBuildPackagesDataBuildsSinglePackageFromShippingDimensionsWhenNoOrderItems(): void
    {
        [$action, $order, $shippingItem] = $this->buildActionWithMocks(139);
        $shippingItem->length = '10';
        $shippingItem->width  = '20';
        $shippingItem->height = '30';
        $order->shouldReceive('getItems')->andReturn([]);
        $order->shouldReceive('shouldBePaidUponDelivery')->andReturn(false);
        $order->currency = 'RUB';

        $packages = $this->invokePrivate($action, 'buildPackagesData');

        self::assertCount(1, $packages);
        self::assertSame(10, $packages[0]['length']);
        self::assertSame(20, $packages[0]['width']);
        self::assertSame(30, $packages[0]['height']);
        self::assertSame([], $packages[0]['items']);
    }

    public function testBuildPackagesDataPreservesExplicitPackagesList(): void
    {
        [$action, $order] = $this->buildActionWithMocks(139);
        $order->shouldReceive('getItems')->andReturn([]);
        $order->shouldReceive('shouldBePaidUponDelivery')->andReturn(false);
        $order->currency = 'RUB';

        $packages = $this->invokePrivate($action, 'buildPackagesData', [
            [
                ['length' => 1, 'width' => 2, 'height' => 3, 'items' => null],
                ['length' => 4, 'width' => 5, 'height' => 6, 'items' => null],
            ],
        ]);

        self::assertCount(2, $packages);
    }
}
