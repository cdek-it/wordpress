<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    use Cdek\Actions\CalculateDeliveryAction;
    use Cdek\Actions\FlushTokenCacheAction;
    use Cdek\Contracts\ExceptionContract;
    use Cdek\Traits\SettingsFields;
    use Throwable;
    use WC_Admin_Settings;
    use WC_Settings_API;
    use WC_Shipping_Method;

    /**
     * @property bool $test_mode
     * @property string $client_id
     * @property string $client_secret
     * @property string $yandex_map_api_key
     * @property string $seller_company
     * @property string $seller_name
     * @property string $seller_phone
     * @property string $seller_email
     * @property bool $international_mode
     * @property string $seller_address
     * @property string $shipper_name
     * @property string $shipper_address
     * @property string $passport_series
     * @property string $passport_number
     * @property string $passport_date_of_issue
     * @property string $passport_organization
     * @property string $tin
     * @property string $passport_date_of_birth
     * @property bool $automate_orders
     * @property string[] $automate_wait_gateways
     * @property string[] $tariff_list
     * @property string $tariff_name
     * @property bool $has_packages_mode
     * @property string $extra_day
     * @property bool $map_auto_close
     * @property string $address
     * @property-read string $city
     * @property string $city_code
     * @property string $token
     * @property string $product_weight_default
     * @property string $product_length_default
     * @property string $product_width_default
     * @property string $product_height_default
     * @property bool $product_package_default_toggle
     * @property bool $services_ban_attachment_inspection
     * @property bool $services_trying_on
     * @property bool $services_part_deliv
     * @property bool $insurance
     * @property string $delivery_price_rules
     * @property string $percentcod
     * @property string $barcode_format
     */
    class ShippingMethod extends WC_Shipping_Method
    {
        use SettingsFields;

        private const DEFAULTS
            = [
                'automate_wait_gateways' => [],
            ];

        public function __construct($instance_id = 0)
        {
            parent::__construct($instance_id);
            $this->id                 = Config::DELIVERY_NAME;
            $this->instance_id        = absint($instance_id);
            $this->method_title       = esc_html__('CDEK Shipping', 'cdekdelivery');
            $this->method_description = esc_html__('Official Shipping Method for Cdek', 'cdekdelivery');
            $this->supports           = [
                'settings',
                'shipping-zones',
                'instance-settings',
            ];
            $this->enabled            = 'yes';
            $this->init();
        }

        final public function init(): void
        {
            $this->title = esc_html__('CDEK Shipping', 'cdekdelivery');
            $this->init_settings();
            $this->init_form_fields();
            add_action("woocommerce_update_options_shipping_$this->id", [$this, 'process_admin_options']);
            add_action("woocommerce_update_options_shipping_$this->id", FlushTokenCacheAction::new());
        }

        public static function factory(?int $instanceId = null): self
        {
            if (!is_null($instanceId)) {
                return new self($instanceId);
            }

            if (isset(WC()->cart)) {
                try {
                    $methods = wc_get_shipping_zone(WC()->cart->get_shipping_packages()[0])->get_shipping_methods(true);

                    foreach ($methods as $method) {
                        if ($method instanceof self) {
                            return $method;
                        }
                    }
                } catch (Throwable $e) {
                }
            }

            return WC()->shipping()->load_shipping_methods()[Config::DELIVERY_NAME];
        }

        /** @noinspection MissingReturnTypeInspection */
        public function __get(string $key)
        {
            $value = $this->get_option($key, self::DEFAULTS[$key] ?? null);

            if ($value === 'yes') {
                return true;
            }

            if ($value === 'no') {
                return false;
            }

            return $value;
        }


        /** @noinspection MissingParameterTypeDeclarationInspection */
        public function __set(string $key, $value): void
        {
            $this->update_option($key, $value);
        }

        /** @noinspection MissingReturnTypeInspection */

        final public function get_option($key, $empty_value = null)
        {
            // Instance options take priority over global options.
            if ($this->instance_id && array_key_exists($key, $this->get_instance_form_fields())) {
                $instanceValue = $this->get_instance_option($key, $empty_value);

                if (array_key_exists("use_$key", $this->get_instance_form_fields())) {
                    if ($this->get_instance_option("use_$key", false) === 'yes') {
                        return $instanceValue;
                    }
                } elseif (!empty($instanceValue) || strpos($key, 'use_') === 0) {
                    return $instanceValue;
                }
            }

            // Return global option.
            return apply_filters(
                'woocommerce_shipping_'.$this->id.'_option',
                WC_Settings_API::get_option($key, $empty_value),
                $key,
                $this,
            );
        }

        public function __isset(string $key): bool
        {
            return $this->get_option($key, self::DEFAULTS[$key] ?? null) !== (self::DEFAULTS[$key] ?? null);
        }

        final public function admin_options(): void
        {
            $error = (new CdekApi)->authGetError();
            if ($error !== null) {
                if ($error === 'invalid_client' || $error === 'unauthorized') {
                    WC_Admin_Settings::add_error(
                        esc_html__(
                            'Error receiving token from CDEK API. Make sure the integration keys are correct',
                            'cdekdelivery',
                        ),
                    );
                } else {
                    WC_Admin_Settings::add_error(
                        sprintf(
                            esc_html__(
                                'Error receiving token from CDEK API. Contact plugin support. Error code: %s',
                                'cdekdelivery',
                            ),
                            $error,
                        ),
                    );
                }
                WC_Admin_Settings::show_messages();
            }

            parent::admin_options();
        }

        final public function calculate_shipping($package = []): void
        {
            try {
                $rates = CalculateDeliveryAction::new()($package, $this->get_instance_id());

                foreach ($rates as $rate) {
                    $this->add_rate($rate);
                }
            } catch (ExceptionContract $e) {
                return;
            }
        }
    }
}
