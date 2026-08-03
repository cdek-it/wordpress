<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Validator;

use Brain\Monkey\Functions;
use Cdek\CdekApi;
use Cdek\CoreApi;
use Cdek\Exceptions\CacheException;
use Cdek\Exceptions\External\ApiException;
use Cdek\Exceptions\External\CoreAuthException;
use Cdek\Exceptions\InvalidPhoneException;
use Cdek\Helpers\CheckoutHelper;
use Cdek\Loader;
use Cdek\MetaKeys;
use Cdek\Model\Tariff;
use Cdek\Tests\TestCase;
use Cdek\Validator\CheckoutValidator;
use Closure;
use Mockery;
use Mockery\MockInterface;
use ReflectionClass;
use Throwable;

final class CheckoutValidatorTest extends TestCase
{
    private const NON_OFFICE_MODE = 999;

    protected function setUp(): void
    {
        parent::setUp();

        $reflection = (new ReflectionClass(Loader::class))->getProperty('pluginName');
        $reflection->setAccessible(true);
        $reflection->setValue(null, 'CDEK Delivery');

        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();
    }

    private function mockRate(array $meta): MockInterface
    {
        $rate = Mockery::mock('WC_Shipping_Rate');
        $rate->shouldReceive('get_meta_data')->andReturn($meta);

        return $rate;
    }

    private function mockCheckoutHelper(?MockInterface $rate, array $values = []): void
    {
        $helper = Mockery::mock('alias:' . CheckoutHelper::class);
        $helper->shouldReceive('getSelectedShippingRate')->andReturn($rate);
        $helper->shouldReceive('getCurrentValue')
               ->andReturnUsing(static fn(string $name) => $values[$name] ?? null);
    }

    private function mockCityCode(string $city, ?string $postal, ?string $result): void
    {
        Mockery::mock('overload:' . CdekApi::class)
               ->shouldReceive('cityCodeGet')
               ->with($city, $postal)
               ->andReturn($result);
    }

    private function mockValidatedPhone(string $phone, ?string $country): void
    {
        Mockery::mock('overload:' . CoreApi::class)
               ->shouldReceive('validatePhone')
               ->with($phone, $country)
               ->andReturn($phone);
    }

    private function mockPhoneValidationThrows(string $phone, ?string $country, Throwable $exception): void
    {
        Mockery::mock('overload:' . CoreApi::class)
               ->shouldReceive('validatePhone')
               ->with($phone, $country)
               ->andThrow($exception);
    }

    public function testInvokeDoesNothingWhenNoShippingRateIsSelected(): void
    {
        $this->mockCheckoutHelper(null);

        Functions\expect('wc_add_notice')->never();
        $this->expectNotToPerformAssertions();

        (new CheckoutValidator())();
    }

    public function testInvokeAddsNoticeWhenOfficeModeAndOfficeNotSelected(): void
    {
        $officeMode = Tariff::listOfficeDeliveryModes()[0];

        $this->mockCheckoutHelper(
            $this->mockRate([
                MetaKeys::TARIFF_MODE => $officeMode,
                MetaKeys::OFFICE_CODE => '',
            ]),
            ['phone' => '+79991234567', 'country' => 'RU'],
        );
        $this->mockValidatedPhone('+79991234567', 'RU');

        Functions\expect('wc_add_notice')
                 ->once()
                 ->with('Order pickup point not selected.', 'error');
        $this->expectNotToPerformAssertions();

        (new CheckoutValidator())();
    }

    public function testInvokeDoesNotAddNoticeWhenOfficeModeAndOfficeSelected(): void
    {
        $officeMode = Tariff::listOfficeDeliveryModes()[0];

        $this->mockCheckoutHelper(
            $this->mockRate([
                MetaKeys::TARIFF_MODE => $officeMode,
                MetaKeys::OFFICE_CODE => 'MSK123',
            ]),
            ['phone' => '+79991234567', 'country' => 'RU'],
        );
        $this->mockValidatedPhone('+79991234567', 'RU');

        Functions\expect('wc_add_notice')->never();
        $this->expectNotToPerformAssertions();

        (new CheckoutValidator())();
    }

    public function testInvokeSkipsOfficeCheckWhenCheckOfficeDisabled(): void
    {
        $officeMode = Tariff::listOfficeDeliveryModes()[0];

        $this->mockCheckoutHelper(
            $this->mockRate([
                MetaKeys::TARIFF_MODE => $officeMode,
                MetaKeys::OFFICE_CODE => '',
            ]),
            ['phone' => '+79991234567', 'country' => 'RU'],
        );
        $this->mockValidatedPhone('+79991234567', 'RU');

        Functions\expect('wc_add_notice')->never();
        $this->expectNotToPerformAssertions();

        (new CheckoutValidator(false))();
    }

    public function testInvokeAddsNoticeWhenAddressIsMissing(): void
    {
        $this->mockCheckoutHelper(
            $this->mockRate([MetaKeys::TARIFF_MODE => self::NON_OFFICE_MODE]),
            [
                'address_1' => '',
                'city'      => 'Москва',
                'postcode'  => '101000',
                'phone'     => '+79991234567',
                'country'   => 'RU',
            ],
        );
        $this->mockCityCode('Москва', '101000', '44');
        $this->mockValidatedPhone('+79991234567', 'RU');

        Functions\expect('wc_add_notice')
                 ->once()
                 ->with('No shipping address.', 'error');
        $this->expectNotToPerformAssertions();

        (new CheckoutValidator())();
    }

    public function testInvokeDoesNotAddAddressNoticeWhenAddressIsPresent(): void
    {
        $this->mockCheckoutHelper(
            $this->mockRate([MetaKeys::TARIFF_MODE => self::NON_OFFICE_MODE]),
            [
                'address_1' => 'Тверская 1',
                'city'      => 'Москва',
                'postcode'  => '101000',
                'phone'     => '+79991234567',
                'country'   => 'RU',
            ],
        );
        $this->mockCityCode('Москва', '101000', '44');
        $this->mockValidatedPhone('+79991234567', 'RU');

        Functions\expect('wc_add_notice')->never();
        $this->expectNotToPerformAssertions();

        (new CheckoutValidator())();
    }

    public function testInvokeAddsNoticeWhenCityCodeCannotBeResolved(): void
    {
        $this->mockCheckoutHelper(
            $this->mockRate([MetaKeys::TARIFF_MODE => self::NON_OFFICE_MODE]),
            [
                'address_1' => 'Тверская 1',
                'city'      => 'Санкт-Петербург',
                'postcode'  => '000000',
                'phone'     => '+79991234567',
                'country'   => 'RU',
            ],
        );
        $this->mockCityCode('Санкт-Петербург', '000000', null);
        $this->mockValidatedPhone('+79991234567', 'RU');

        Functions\expect('wc_add_notice')
                 ->once()
                 ->with('Failed to determine locality in Санкт-Петербург 000000', 'error');
        $this->expectNotToPerformAssertions();

        (new CheckoutValidator())();
    }

    public function testInvokeDoesNotAddCityNoticeWhenCityCodeIsResolved(): void
    {
        $this->mockCheckoutHelper(
            $this->mockRate([MetaKeys::TARIFF_MODE => self::NON_OFFICE_MODE]),
            [
                'address_1' => 'Тверская 1',
                'city'      => 'Москва',
                'postcode'  => '101000',
                'phone'     => '+79991234567',
                'country'   => 'RU',
            ],
        );
        $this->mockCityCode('Москва', '101000', '44');
        $this->mockValidatedPhone('+79991234567', 'RU');

        Functions\expect('wc_add_notice')->never();
        $this->expectNotToPerformAssertions();

        (new CheckoutValidator())();
    }

    public function testInvokeAddsNoticeWhenPhoneIsEmpty(): void
    {
        $officeMode = Tariff::listOfficeDeliveryModes()[0];

        $this->mockCheckoutHelper(
            $this->mockRate([
                MetaKeys::TARIFF_MODE => $officeMode,
                MetaKeys::OFFICE_CODE => 'MSK123',
            ]),
            ['phone' => ''],
        );

        Functions\expect('wc_add_notice')
                 ->once()
                 ->with('Phone number is required.', 'error');
        $this->expectNotToPerformAssertions();

        (new CheckoutValidator())();
    }

    public function testInvokeDoesNotAddNoticeWhenPhoneIsValid(): void
    {
        $officeMode = Tariff::listOfficeDeliveryModes()[0];

        $this->mockCheckoutHelper(
            $this->mockRate([
                MetaKeys::TARIFF_MODE => $officeMode,
                MetaKeys::OFFICE_CODE => 'MSK123',
            ]),
            ['phone' => '+79991234567', 'country' => 'RU'],
        );
        $this->mockValidatedPhone('+79991234567', 'RU');

        Functions\expect('wc_add_notice')->never();
        $this->expectNotToPerformAssertions();

        (new CheckoutValidator())();
    }

    /**
     * @dataProvider silentlyCaughtPhoneExceptionProvider
     */
    public function testInvokeSilentlyReturnsWhenPhoneValidationFailsWithInfrastructureException(
        Closure $exceptionFactory
    ): void {
        $officeMode = Tariff::listOfficeDeliveryModes()[0];

        $this->mockCheckoutHelper(
            $this->mockRate([
                MetaKeys::TARIFF_MODE => $officeMode,
                MetaKeys::OFFICE_CODE => 'MSK123',
            ]),
            ['phone' => '+79991234567', 'country' => 'RU'],
        );
        $this->mockPhoneValidationThrows('+79991234567', 'RU', $exceptionFactory());

        Functions\expect('wc_add_notice')->never();
        $this->expectNotToPerformAssertions();

        (new CheckoutValidator())();
    }

    public static function silentlyCaughtPhoneExceptionProvider(): array
    {
        return [
            'core auth exception' => [static fn() => new CoreAuthException()],
            'api exception'       => [static fn() => new ApiException([])],
            'cache exception'     => [static fn() => new CacheException('/tmp/cache')],
        ];
    }

    public function testInvokeAddsNoticeWithExceptionMessageWhenPhoneValidationFailsWithOtherException(): void
    {
        $officeMode = Tariff::listOfficeDeliveryModes()[0];

        $this->mockCheckoutHelper(
            $this->mockRate([
                MetaKeys::TARIFF_MODE => $officeMode,
                MetaKeys::OFFICE_CODE => 'MSK123',
            ]),
            ['phone' => 'bad-phone', 'country' => 'RU'],
        );

        $exception = new InvalidPhoneException('bad-phone');
        $this->mockPhoneValidationThrows('bad-phone', 'RU', $exception);

        Functions\expect('wc_add_notice')
                 ->once()
                 ->with($exception->getMessage(), 'error');
        $this->expectNotToPerformAssertions();

        (new CheckoutValidator())();
    }
}
