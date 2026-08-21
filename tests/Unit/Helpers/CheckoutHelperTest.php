<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Helpers;

use Brain\Monkey\Functions;
use Cdek\Config;
use Cdek\Fieldsets\GeneralOrderFields;
use Cdek\Fieldsets\InternationalOrderFields;
use Cdek\Helpers\CheckoutHelper;
use Cdek\MetaKeys;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Exception;
use Mockery;

final class CartWithRealShippingMethodsDouble
{
    private array $shippingMethods;
    private $calculateShippingResult;

    public function __construct(array $shippingMethods, $calculateShippingResult = null)
    {
        $this->shippingMethods         = $shippingMethods;
        $this->calculateShippingResult = $calculateShippingResult;
    }

    public function get_shipping_methods(): array
    {
        return $this->shippingMethods;
    }

    public function calculate_shipping()
    {
        return $this->calculateShippingResult;
    }
}

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class CheckoutHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_REQUEST = [];
        $_GET     = [];
        unset($_SERVER['REQUEST_URI']);

        Functions\when('wp_strip_all_tags')->returnArg();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('esc_html__')->returnArg();
    }

    private function mockWc(?array $session, ?array $checkoutValues, ?array $customer): void
    {
        $wc = Mockery::mock();

        if ($session !== null) {
            $sessionMock = Mockery::mock();
            if (($session['throws'] ?? null) !== null) {
                $sessionMock->shouldReceive('get')->andThrow($session['throws']);
            } else {
                $sessionMock->shouldReceive('get')->andReturn($session['value']);
            }
            $wc->session = $sessionMock;
        }

        if ($checkoutValues !== null) {
            $checkoutMock = Mockery::mock();
            $checkoutMock->shouldReceive('get_value')
                         ->andReturnUsing(static fn(string $key) => $checkoutValues[$key] ?? null);
            $wc->shouldReceive('checkout')->andReturn($checkoutMock);
        }

        if ($customer !== null) {
            $customerMock = Mockery::mock();
            if (($customer['throws'] ?? null) !== null) {
                $customerMock->shouldReceive('get_meta')->andThrow($customer['throws']);
            } else {
                $customerMock->shouldReceive('get_meta')->andReturn($customer['value']);
            }
            $wc->customer = $customerMock;
        }

        Functions\when('WC')->justReturn($wc);
    }

    public function testGetCurrentValueReturnsSessionValueWhenPresent(): void
    {
        $this->mockWc(['value' => 'from-session'], null, null);

        self::assertSame('from-session', CheckoutHelper::getCurrentValue('city'));
    }

    public function testGetCurrentValueFallsBackToShippingWhenSessionThrows(): void
    {
        $this->mockWc(['throws' => new Exception('boom')], ['shipping_city' => 'From Shipping'], null);

        self::assertSame('From Shipping', CheckoutHelper::getCurrentValue('city'));
    }

    public function testGetCurrentValueFallsBackToShippingWhenSessionEmpty(): void
    {
        $this->mockWc(['value' => ''], ['shipping_city' => 'From Shipping'], null);

        self::assertSame('From Shipping', CheckoutHelper::getCurrentValue('city'));
    }

    public function testGetCurrentValueReturnsBillingValueWhenShippingEmpty(): void
    {
        $this->mockWc(
            ['value' => null],
            ['shipping_city' => '', 'billing_city' => 'From Billing'],
            null,
        );

        self::assertSame('From Billing', CheckoutHelper::getCurrentValue('city'));
    }

    public function testGetCurrentValueReturnsExtensionsRequestValueWhenCheckoutFieldsEmpty(): void
    {
        $_REQUEST['extensions'][Config::DELIVERY_NAME]['city'] = 'From Extensions';

        $this->mockWc(['value' => null], ['shipping_city' => '', 'billing_city' => ''], null);

        self::assertSame('From Extensions', CheckoutHelper::getCurrentValue('city'));
    }

    public function testGetCurrentValueReturnsPlainRequestValueWhenExtensionsEmpty(): void
    {
        $_REQUEST['city'] = 'From Request';

        $this->mockWc(['value' => null], ['shipping_city' => '', 'billing_city' => ''], null);

        self::assertSame('From Request', CheckoutHelper::getCurrentValue('city'));
    }

    public function testGetCurrentValueReturnsCustomerMetaWhenRequestEmpty(): void
    {
        $this->mockWc(
            ['value' => null],
            ['shipping_city' => '', 'billing_city' => ''],
            ['value' => 'From Customer Meta'],
        );

        self::assertSame('From Customer Meta', CheckoutHelper::getCurrentValue('city'));
    }

    public function testGetCurrentValueFallsBackToFinalCheckoutValueWhenCustomerMetaThrows(): void
    {
        $this->mockWc(
            ['value' => null],
            ['shipping_city' => '', 'billing_city' => '', 'city' => 'From Final Fallback'],
            ['throws' => new Exception('boom')],
        );

        self::assertSame('From Final Fallback', CheckoutHelper::getCurrentValue('city'));
    }

    public function testGetCurrentValueReturnsDefaultValueWhenEverythingIsEmpty(): void
    {
        $this->mockWc(
            ['value' => null],
            ['shipping_city' => '', 'billing_city' => '', 'city' => ''],
            ['value' => null],
        );

        self::assertSame('fallback-default', CheckoutHelper::getCurrentValue('city', 'fallback-default'));
        self::assertNull(CheckoutHelper::getCurrentValue('city'));
    }

    public function testPassOfficeToCartPackagesSetsOfficeCodeOnEachPackageDestination(): void
    {
        $this->mockWc(
            ['value' => null],
            ['shipping_office_code' => '', 'billing_office_code' => ''],
            ['value' => null],
        );
        $_REQUEST['office_code'] = 'MSK123';

        $packages = [
            ['destination' => ['country' => 'RU']],
            ['destination' => ['country' => 'RU', 'city' => 'Moscow']],
        ];

        $result = CheckoutHelper::passOfficeToCartPackages($packages);

        self::assertSame('MSK123', $result[0]['destination'][MetaKeys::OFFICE_CODE]);
        self::assertSame('RU', $result[0]['destination']['country']);
        self::assertSame('MSK123', $result[1]['destination'][MetaKeys::OFFICE_CODE]);
        self::assertSame('Moscow', $result[1]['destination']['city']);
    }

    public function testPassOfficeToCartPackagesLeavesDestinationUnchangedWhenOfficeCodeIsEmpty(): void
    {
        $this->mockWc(
            ['value' => null],
            ['shipping_office_code' => '', 'billing_office_code' => '', 'office_code' => ''],
            ['value' => null],
        );

        $packages = [['destination' => ['country' => 'RU']]];

        $result = CheckoutHelper::passOfficeToCartPackages($packages);

        self::assertSame(['destination' => ['country' => 'RU']], $result[0]);
    }

    public function testGetSelectedShippingRateReturnsNullWhenCartIsNull(): void
    {
        $wc      = Mockery::mock();
        $wc->cart = null;
        Functions\when('WC')->justReturn($wc);

        self::assertNull(CheckoutHelper::getSelectedShippingRate());
    }

    public function testGetSelectedShippingRateReturnsMatchingRateFromGetShippingMethods(): void
    {
        $nonMatching = Mockery::mock('WC_Shipping_Rate');
        $nonMatching->shouldReceive('get_method_id')->andReturn('flat_rate');

        $matching = Mockery::mock('WC_Shipping_Rate');
        $matching->shouldReceive('get_method_id')->andReturn(Config::DELIVERY_NAME);

        $wc       = Mockery::mock();
        $wc->cart = new CartWithRealShippingMethodsDouble([$nonMatching, $matching]);
        Functions\when('WC')->justReturn($wc);

        self::assertSame($matching, CheckoutHelper::getSelectedShippingRate());
    }

    public function testGetSelectedShippingRateFallsBackToCalculateShippingWhenGetShippingMethodsIsEmpty(): void
    {
        $matching = Mockery::mock('WC_Shipping_Rate');
        $matching->shouldReceive('get_method_id')->andReturn(Config::DELIVERY_NAME);

        $wc       = Mockery::mock();
        $wc->cart = new CartWithRealShippingMethodsDouble([], [$matching]);
        Functions\when('WC')->justReturn($wc);

        self::assertSame($matching, CheckoutHelper::getSelectedShippingRate());
    }

    public function testGetSelectedShippingRateReturnsNullWhenCalculateShippingReturnsNull(): void
    {
        $wc       = Mockery::mock();
        $wc->cart = new CartWithRealShippingMethodsDouble([], null);
        Functions\when('WC')->justReturn($wc);

        self::assertNull(CheckoutHelper::getSelectedShippingRate());
    }

    public function testGetSelectedShippingRateReturnsNullWhenNoMethodMatches(): void
    {
        $nonMatching = Mockery::mock('WC_Shipping_Rate');
        $nonMatching->shouldReceive('get_method_id')->andReturn('flat_rate');

        $wc       = Mockery::mock();
        $wc->cart = new CartWithRealShippingMethodsDouble([$nonMatching]);
        Functions\when('WC')->justReturn($wc);

        self::assertNull(CheckoutHelper::getSelectedShippingRate());
    }

    public function testGetSelectedShippingRateUsesExplicitCartArgumentInsteadOfWcCart(): void
    {
        $matching = Mockery::mock('WC_Shipping_Rate');
        $matching->shouldReceive('get_method_id')->andReturn(Config::DELIVERY_NAME);

        $cart = Mockery::mock('WC_Cart');
        $cart->shouldReceive('calculate_shipping')->andReturn([$matching]);

        self::assertSame($matching, CheckoutHelper::getSelectedShippingRate($cart));
    }

    public function testIsShippingRateSuitableReturnsTrueForMatchingMethodId(): void
    {
        $rate = Mockery::mock('WC_Shipping_Rate');
        $rate->shouldReceive('get_method_id')->andReturn(Config::DELIVERY_NAME);

        self::assertTrue(CheckoutHelper::isShippingRateSuitable($rate));
    }

    public function testIsShippingRateSuitableReturnsFalseForOtherMethodId(): void
    {
        $rate = Mockery::mock('WC_Shipping_Rate');
        $rate->shouldReceive('get_method_id')->andReturn('flat_rate');

        self::assertFalse(CheckoutHelper::isShippingRateSuitable($rate));
    }

    private function mockSelectedRateAvailable(array $originalFields, bool $internationalMode): void
    {
        $rate = Mockery::mock('WC_Shipping_Rate');
        $rate->shouldReceive('get_method_id')->andReturn(Config::DELIVERY_NAME);

        $checkout = Mockery::mock();
        $checkout->shouldReceive('get_checkout_fields')->with('billing')->andReturn($originalFields);

        $wc       = Mockery::mock();
        $wc->cart = new CartWithRealShippingMethodsDouble([$rate]);
        $wc->shouldReceive('checkout')->andReturn($checkout);
        Functions\when('WC')->justReturn($wc);

        $methodInstance                  = Mockery::mock();
        $methodInstance->international_mode = $internationalMode;

        Mockery::mock('alias:' . ShippingMethod::class)
               ->shouldReceive('factory')
               ->andReturn($methodInstance);
    }

    public function testRestoreFieldsReturnsFieldsUnchangedWhenNoRateIsSelected(): void
    {
        $wc       = Mockery::mock();
        $wc->cart = null;
        Functions\when('WC')->justReturn($wc);

        $fields = ['billing' => ['some_existing_field' => ['x' => 1]]];

        self::assertSame($fields, CheckoutHelper::restoreFields($fields));
    }

    public function testRestoreFieldsFillsGeneralFieldsPreferringOriginalFieldsWhenPresent(): void
    {
        $originalFields = [
            'billing_first_name' => ['label' => 'Original First Name', 'required' => false],
        ];

        $this->mockSelectedRateAvailable($originalFields, false);

        $result = CheckoutHelper::restoreFields(['billing' => []]);

        $fieldset = new GeneralOrderFields();

        self::assertSame($fieldset->getFieldDefinition('billing_address_1'), $result['billing']['billing_address_1']);
        self::assertSame($fieldset->getFieldDefinition('billing_address_2'), $result['billing']['billing_address_2']);
        self::assertSame(
            array_merge($fieldset->getFieldDefinition('billing_phone'), ['required' => true]),
            $result['billing']['billing_phone'],
        );
        self::assertSame(
            array_merge($fieldset->getFieldDefinition('billing_city'), ['required' => true]),
            $result['billing']['billing_city'],
        );
        self::assertSame(
            ['label' => 'Original First Name', 'required' => true],
            $result['billing']['billing_first_name'],
        );

        self::assertArrayNotHasKey('passport_series', $result['billing']);
    }

    public function testRestoreFieldsPreservesPreExistingFieldValueButForcesRequiredFlag(): void
    {
        $this->mockSelectedRateAvailable([], false);

        $fields = ['billing' => ['billing_phone' => ['label' => 'Custom Phone', 'custom' => true]]];

        $result = CheckoutHelper::restoreFields($fields);

        self::assertSame(
            ['label' => 'Custom Phone', 'custom' => true, 'required' => true],
            $result['billing']['billing_phone'],
        );
    }

    public function testRestoreFieldsAlsoFillsInternationalFieldsWhenApplicable(): void
    {
        $this->mockSelectedRateAvailable([], true);

        $result = CheckoutHelper::restoreFields(['billing' => []]);

        $international = new InternationalOrderFields();

        self::assertSame(
            $international->getFieldDefinition('passport_series'),
            $result['billing']['passport_series'],
        );
    }

    public function testIsCheckoutRequestReturnsFalseByDefault(): void
    {
        self::assertFalse(CheckoutHelper::isCheckoutRequest());
    }

    public function testIsCheckoutRequestReturnsFalseForCartRoute(): void
    {
        $_GET['rest_route']     = '/wc/store/v1/cart/add-item';
        $_SERVER['REQUEST_URI'] = '/index.php?rest_route=/wc/store/v1/cart/add-item';

        self::assertFalse(CheckoutHelper::isCheckoutRequest());
    }

    public function testIsCheckoutRequestReturnsFalseForBatchRoute(): void
    {
        $_GET['rest_route']     = '/wc/store/v1/batch';
        $_SERVER['REQUEST_URI'] = '/index.php?rest_route=/wc/store/v1/batch';

        self::assertFalse(CheckoutHelper::isCheckoutRequest());
    }

    public function testIsCheckoutRequestReturnsTrueForRestRouteQueryVar(): void
    {
        $_GET['rest_route'] = '/wc/store/v1/checkout';

        self::assertTrue(CheckoutHelper::isCheckoutRequest());
    }

    public function testIsCheckoutRequestReturnsTrueForCheckoutOrderSubRoute(): void
    {
        $_GET['rest_route'] = '/wc/store/v1/checkout/123';

        self::assertTrue(CheckoutHelper::isCheckoutRequest());
    }

    public function testIsCheckoutRequestReturnsTrueForPrettyPermalinkRequestUri(): void
    {
        $_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout';

        self::assertTrue(CheckoutHelper::isCheckoutRequest());
    }

    public function testIsCheckoutRequestReturnsTrueForClassicCheckoutPage(): void
    {
        Functions\when('is_checkout')->justReturn(true);

        self::assertTrue(CheckoutHelper::isCheckoutRequest());
    }

    public function testIsCheckoutRequestReturnsFalseWhenNotCheckoutPageAndNoRouteMatches(): void
    {
        Functions\when('is_checkout')->justReturn(false);

        self::assertFalse(CheckoutHelper::isCheckoutRequest());
    }
}
