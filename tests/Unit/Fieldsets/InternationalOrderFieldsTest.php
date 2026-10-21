<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Fieldsets;

use Brain\Monkey\Functions;
use Cdek\Fieldsets\InternationalOrderFields;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Mockery;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class InternationalOrderFieldsTest extends TestCase
{
    private array $originalPost;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalPost = $_POST;
        $_POST              = [];

        Functions\when('wc_clean')->returnArg();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('wc_ship_to_billing_address_only')->justReturn(false);
    }

    protected function tearDown(): void
    {
        $_POST = $this->originalPost;

        parent::tearDown();
    }

    /**
     * @param string $customerCountry страна, которую WC отдаёт из профиля/сессии покупателя
     */
    private function mockCheckout(bool $internationalMode, string $customerCountry): void
    {
        $session = Mockery::mock();
        $session->shouldReceive('get')->andReturn(null);

        $checkout = Mockery::mock();
        $checkout->shouldReceive('get_value')->andReturn($customerCountry);

        $wc          = Mockery::mock();
        $wc->session = $session;
        $wc->shouldReceive('checkout')->andReturn($checkout);
        Functions\when('WC')->justReturn($wc);

        $methodInstance                     = Mockery::mock();
        $methodInstance->international_mode = $internationalMode;

        Mockery::mock('alias:' . ShippingMethod::class)
               ->shouldReceive('factory')
               ->andReturn($methodInstance);
    }

    public function testIsNotApplicableWhenModeDisabled(): void
    {
        $this->mockCheckout(false, 'KZ');

        self::assertFalse((new InternationalOrderFields())->isApplicable());
    }

    public function testIsApplicableForForeignCountryWhenModeEnabled(): void
    {
        $this->mockCheckout(true, 'KZ');

        self::assertTrue((new InternationalOrderFields())->isApplicable());
    }

    public function testIsNotApplicableForRussiaWhenModeEnabled(): void
    {
        $this->mockCheckout(true, 'RU');

        $fields = new InternationalOrderFields();

        self::assertTrue($fields->isDomestic());
        self::assertFalse($fields->isApplicable());
    }

    public function testIsNotApplicableWhenBillingCountryIsRussiaAndStaleShippingCountryIsForeign(): void
    {
        $_POST['billing_country'] = 'RU';

        $this->mockCheckout(true, 'KZ');

        $fields = new InternationalOrderFields();

        self::assertTrue($fields->isDomestic());
        self::assertFalse($fields->isApplicable());
    }

    public function testUsesCountryFromProfileWhenShippingToDifferentAddress(): void
    {
        $_POST['billing_country']          = 'RU';
        $_POST['ship_to_different_address'] = '1';

        // WC уже подставил posted shipping_country в get_value()
        $this->mockCheckout(true, 'KZ');

        self::assertTrue((new InternationalOrderFields())->isApplicable());
    }

    public function testIsApplicableWhenBillingCountryIsForeign(): void
    {
        $_POST['billing_country'] = 'KZ';

        $this->mockCheckout(true, 'RU');

        self::assertTrue((new InternationalOrderFields())->isApplicable());
    }
}
