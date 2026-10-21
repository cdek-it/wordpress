<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit;

use Brain\Monkey\Functions;
use Cdek\Config;
use Cdek\Loader;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Mockery;
use ReflectionClass;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ShippingMethodTest extends TestCase
{
    /** @var bool значение, которое вернёт doing_action() */
    private bool $doingUpdateAction = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->declareWooCommerceStubs();

        $debug = (new ReflectionClass(Loader::class))->getProperty('debug');
        $debug->setAccessible(true);
        $debug->setValue(null, false);

        $this->doingUpdateAction = false;

        Functions\when('esc_html__')->returnArg();
        Functions\when('esc_html')->returnArg();
        Functions\when('absint')->alias(static fn($value) => abs((int)$value));
        Functions\when('add_action')->justReturn(true);
        Functions\when('get_option')->justReturn('kg');
        Functions\when('apply_filters')->alias(static fn(string $hook, $value) => $value);
        Functions\when('doing_action')->alias(fn(string $action): bool => $this->doingUpdateAction
                                                                          && $action === 'woocommerce_update_options_shipping_' . Config::DELIVERY_NAME);
        Functions\when('wc_format_decimal')->alias(static fn($value) => (string)$value);
    }

    private function declareWooCommerceStubs(): void
    {
        eval(<<<'PHP'
            class WC_Settings_API
            {
                /** @var array<string, mixed> глобальные настройки "в базе" */
                public static array $storedSettings = [];
                /** @var array<string, mixed> instance-настройки "в базе" */
                public static array $storedInstanceSettings = [];

                public $id = '';
                public $form_fields = [];
                public $settings = [];
                public $errors = [];

                public function init_settings(): void
                {
                    $this->settings = self::$storedSettings;
                }

                public function get_form_fields(): array
                {
                    return $this->form_fields;
                }

                public function get_option($key, $empty_value = null)
                {
                    if (empty($this->settings)) {
                        $this->init_settings();
                    }

                    if (!isset($this->settings[$key])) {
                        $this->settings[$key] = '';
                    }

                    if (!is_null($empty_value) && '' === $this->settings[$key]) {
                        $this->settings[$key] = $empty_value;
                    }

                    return $this->settings[$key];
                }

                public function update_option($key, $value = '')
                {
                    $this->settings[$key]      = $value;
                    self::$storedSettings[$key] = $value;

                    return true;
                }

                public function add_error($error): void
                {
                    $this->errors[] = $error;
                }
            }

            class WC_Shipping_Method extends WC_Settings_API
            {
                public $instance_id = 0;
                public $method_title = '';
                public $method_description = '';
                public $supports = [];
                public $enabled = 'yes';
                public $title = '';
                public $instance_form_fields = [];
                public $instance_settings = [];

                public function __construct($instance_id = 0)
                {
                }

                public function get_instance_form_fields(): array
                {
                    return $this->instance_form_fields;
                }

                public function init_instance_settings(): void
                {
                    $this->instance_settings = self::$storedInstanceSettings;
                }

                public function get_instance_option($key, $empty_value = null)
                {
                    if (empty($this->instance_settings)) {
                        $this->init_instance_settings();
                    }

                    $value = $this->instance_settings[$key] ?? '';

                    return ($value === '' && !is_null($empty_value)) ? $empty_value : $value;
                }
            }
            PHP);
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $instanceSettings
     */
    private function method(array $settings = [], int $instanceId = 0, array $instanceSettings = []): ShippingMethod
    {
        \WC_Settings_API::$storedSettings         = $settings;
        \WC_Settings_API::$storedInstanceSettings = $instanceSettings;

        return new ShippingMethod($instanceId);
    }

    public function testConstructorConfiguresMethodAndRemembersInstance(): void
    {
        $method = $this->method([], 5);

        self::assertSame(Config::DELIVERY_NAME, $method->id);
        self::assertSame(5, $method->instance_id);
        self::assertSame('yes', $method->enabled);
        self::assertSame(['settings', 'shipping-zones', 'instance-settings'], $method->supports);
        $wc = Mockery::mock();
        Functions\when('WC')->justReturn($wc);

        self::assertSame($method, ShippingMethod::factory());
    }

    public function testFactoryWithInstanceIdCreatesNewMethodForThatInstance(): void
    {
        $first  = $this->method();
        $second = ShippingMethod::factory(9);

        self::assertNotSame($first, $second);
        self::assertSame(9, $second->instance_id);
    }

    public function testInitSettingsClearsStoredTokenWhileSettingsAreBeingSaved(): void
    {
        $this->doingUpdateAction = true;

        $method = $this->method(['token' => 'encrypted', 'client_id' => 'abc']);

        self::assertNull($method->settings['token']);
        self::assertSame('abc', $method->settings['client_id']);
    }

    public function testInitSettingsKeepsStoredTokenInNormalRequests(): void
    {
        $method = $this->method(['token' => 'encrypted']);

        self::assertSame('encrypted', $method->settings['token']);
    }

    public function testInitInstanceSettingsClearsTokenWhileSettingsAreBeingSaved(): void
    {
        $this->doingUpdateAction = true;

        $method = $this->method([], 3, ['token' => 'instance-token']);
        $method->init_instance_settings();

        self::assertNull($method->instance_settings['token']);
    }

    /**
     * @dataProvider storedValueProvider
     * @param mixed $stored
     * @param mixed $expected
     */
    public function testGetConvertsYesNoToBooleanAndKeepsOtherValues($stored, $expected): void
    {
        $method = $this->method(['option' => $stored]);

        self::assertSame($expected, $method->option);
    }

    public static function storedValueProvider(): array
    {
        return [
            'yes'            => ['yes', true],
            'no'             => ['no', false],
            'string'         => ['some value', 'some value'],
            'numeric string' => ['0', '0'],
            'array'          => [['a', 'b'], ['a', 'b']],
        ];
    }

    public function testGetUsesDefaultForOptionsThatHaveOne(): void
    {
        $method = $this->method([]);

        self::assertSame([], $method->automate_wait_gateways);
    }

    public function testGetReturnsStoredValueInsteadOfDefault(): void
    {
        $method = $this->method(['automate_wait_gateways' => ['cod']]);

        self::assertSame(['cod'], $method->automate_wait_gateways);
    }

    public function testSetWritesValueThroughUpdateOption(): void
    {
        $method = $this->method([]);

        $method->token = 'new-token';

        self::assertSame('new-token', $method->token);
        self::assertSame('new-token', \WC_Settings_API::$storedSettings['token']);
    }

    public function testIssetIsTrueWhenOptionDiffersFromItsDefault(): void
    {
        $method = $this->method(['automate_wait_gateways' => ['cod']]);

        self::assertTrue(isset($method->automate_wait_gateways));
    }

    public function testIssetIsFalseWhenOptionEqualsItsDefault(): void
    {
        $method = $this->method([]);

        self::assertFalse(isset($method->automate_wait_gateways));
    }

    public function testGetOptionReturnsGlobalValueWithoutInstance(): void
    {
        $method = $this->method(['delivery_price_rules' => 'global-rules'], 0, ['delivery_price_rules' => 'zone-rules']);

        self::assertSame('global-rules', $method->get_option('delivery_price_rules'));
    }

    public function testGetOptionPassesGlobalValueThroughFilter(): void
    {
        $captured = null;
        Functions\when('apply_filters')->alias(
            function (string $hook, $value, string $key, $method) use (&$captured) {
                $captured = [$hook, $value, $key, $method];

                return 'filtered';
            },
        );

        $method = $this->method(['city_code' => '44']);

        self::assertSame('filtered', $method->get_option('city_code'));
        self::assertSame(['woocommerce_shipping_official_cdek_option', '44', 'city_code', $method], $captured);
    }

    public function testGetOptionPrefersInstanceValueWhenZoneOverrideIsEnabled(): void
    {
        $method = $this->method(
            ['delivery_price_rules' => 'global-rules'],
            3,
            ['use_delivery_price_rules' => 'yes', 'delivery_price_rules' => 'zone-rules'],
        );

        self::assertSame('zone-rules', $method->get_option('delivery_price_rules'));
    }

    public function testGetOptionFallsBackToGlobalValueWhenZoneOverrideIsDisabled(): void
    {
        $method = $this->method(
            ['delivery_price_rules' => 'global-rules'],
            3,
            ['use_delivery_price_rules' => 'no', 'delivery_price_rules' => 'zone-rules'],
        );

        self::assertSame('global-rules', $method->get_option('delivery_price_rules'));
    }

    public function testGetOptionIgnoresInstanceValueForOptionsThatAreNotInstanceFields(): void
    {
        $method = $this->method(['client_id' => 'global-id'], 3, ['client_id' => 'zone-id']);

        self::assertSame('global-id', $method->get_option('client_id'));
    }

    /**
     * @dataProvider validRatioProvider
     */
    public function testVolumeRatioAcceptsValuesFromOneToOnePointFifteen(string $input, string $expected): void
    {
        Functions\when('wc_format_decimal')->alias(static fn($v) => (string)$v);

        $method = $this->method(['product_package_volume_ratio' => '1']);

        self::assertSame($expected, $method->validate_product_package_volume_ratio_field('product_package_volume_ratio', $input));
        self::assertSame([], $method->errors);
    }

    public static function validRatioProvider(): array
    {
        return [
            'lower bound'   => ['1', '1'],
            'decimal point' => ['1.05', '1.05'],
            'decimal comma' => ['1,1', '1.1'],
            'upper bound'   => ['1.15', '1.15'],
        ];
    }

    /**
     * @dataProvider invalidRatioProvider
     */
    public function testVolumeRatioRejectsOutOfRangeValuesAndKeepsStoredOne(?string $input): void
    {
        $method = $this->method(['product_package_volume_ratio' => '1.1']);

        $result = $method->validate_product_package_volume_ratio_field('product_package_volume_ratio', $input);

        self::assertSame('1.1', $result);
        self::assertSame(['Package volume safety ratio must be between 1 and 1.15.'], $method->errors);
    }

    public static function invalidRatioProvider(): array
    {
        return [
            'below one'   => ['0.99'],
            'above limit' => ['1.16'],
            'empty'       => [''],
            'null'        => [null],
        ];
    }
}
