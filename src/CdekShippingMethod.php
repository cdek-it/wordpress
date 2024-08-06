<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    use Cdek\Actions\CalculateDeliveryAction;
    use Cdek\Actions\FlushTokenCacheAction;
    use Cdek\Enums\BarcodeFormat;
    use Cdek\Exceptions\CdekException;
    use Cdek\Model\Tariff;
    use WC_Settings_API;
    use WC_Shipping_Method;

    class CdekShippingMethod extends WC_Shipping_Method
    {
        private static bool $settingsMutex = false;

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
            add_action("woocommerce_update_options_shipping_$this->id", new FlushTokenCacheAction);
        }

        final public function init_form_fields(): void
        {
            $this->instance_form_fields = [
                'use_delivery_price_rules' => [
                    'title' => esc_html__('Use delivery amount display rules for a zone', 'cdekdelivery'),
                    'type'  => 'checkbox',
                ],
                'delivery_price_rules'     => [
                    'title' => esc_html__('Rules for displaying the delivery amount', 'cdekdelivery'),
                    'label' => '',
                    'type'  => 'hidden',
                ],
            ];

            if (!self::$settingsMutex &&
                isset($_GET['tab'], $_GET['section']) &&
                $_GET['tab'] === 'shipping' &&
                $_GET['section'] = $this->id) {
                self::$settingsMutex = true;

                $availableGateways
                    = array_map(static fn($g) => $g->title, WC()->payment_gateways()->payment_gateways());

                self::$settingsMutex = false;
            } else {
                $availableGateways = [];
            }

            $this->form_fields = [
                'auth_block_name'                    => [
                    'title' => '<h3 style="text-align: center;">'.esc_html__('Authorization', 'cdekdelivery').'</h3>',
                    'type'  => 'title',
                    'class' => 'cdek_setting_block_name',
                ],
                'test_mode'                          => [
                    'title'       => esc_html__('Test mode', 'cdekdelivery'),
                    'type'        => 'checkbox',
                    'default'     => false,
                    'desc_tip'    => true,
                    'description' => esc_html__('When enabled, the test API is used', 'cdekdelivery'),
                ],
                'client_id'                          => [
                    'title'             => esc_html__('Identifier', 'cdekdelivery'),
                    'type'              => 'text',
                    'custom_attributes' => [
                        'required' => true,
                    ],
                ],
                'client_secret'                      => [
                    'title'             => esc_html__('The secret key', 'cdekdelivery'),
                    'type'              => 'text',
                    'custom_attributes' => [
                        'required' => true,
                    ],
                ],
                'yandex_map_api_key'                 => [
                    'type'              => 'text',
                    'title'             => esc_html__('Yandex map key', 'cdekdelivery'),
                    'description'       => str_replace([
                                                           esc_html('<a>'),
                                                           esc_html('</a>'),
                                                       ], [
                                                           '<a rel="noopener nofollower" href="https://yandex.ru/dev/jsapi-v2-1/doc/ru/#get-api-key" target="_blank">',
                                                           '</a>',
                                                       ],
                        esc_html__('Yandex API access key. The generation process is described on <a>the page</a>',
                                   'cdekdelivery')),
                    'custom_attributes' => [
                        'required' => true,
                    ],
                ],
                'seller_block_name'                  => [
                    'title' => '<h3 style="text-align: center;">'.esc_html__('Client', 'cdekdelivery').'</h3>',
                    'type'  => 'title',
                    'class' => 'cdek_setting_block_name',
                ],
                'seller_company'                     => [
                    'title'             => esc_html__('Company name', 'cdekdelivery'),
                    'type'              => 'text',
                    'custom_attributes' => [
                        'required' => true,
                    ],
                ],
                'seller_name'                        => [
                    'title'             => esc_html__('Full name', 'cdekdelivery'),
                    'type'              => 'text',
                    'custom_attributes' => [
                        'required' => true,
                    ],
                ],
                'seller_phone'                       => [
                    'title'             => esc_html__('Phone', 'cdekdelivery'),
                    'type'              => 'text',
                    'desc_tip'          => true,
                    'description'       => esc_html__('Must be transmitted in international format: country code (for Russia +7) and the number itself (10 or more digits)',
                                                      'cdekdelivery'),
                    'custom_attributes' => [
                        'required' => true,
                    ],
                ],
                'seller_email'                       => [
                    'title'             => esc_html__('Company email address', 'cdekdelivery'),
                    'type'              => 'text',
                    'custom_attributes' => [
                        'required' => true,
                        'type'     => 'email',
                    ],
                ],
                'international_title'                => [
                    'title' => esc_html__('International orders', 'cdekdelivery'),
                    'type'  => 'title',
                ],
                'international_mode'                 => [
                    'title'       => esc_html__('Enable international order mode', 'cdekdelivery'),
                    'type'        => 'checkbox',
                    'desc_tip'    => true,
                    'description' => esc_html__('When the international orders mode is enabled, additional fields will appear on the checkout page: passport series, passport number, date of issue, department, TIN, date of birth.',
                                                'cdekdelivery'),
                    'default'     => 'no',
                ],
                'seller_address'                     => [
                    'title'       => esc_html__('True seller address', 'cdekdelivery'),
                    'type'        => 'text',
                    'desc_tip'    => true,
                    'description' => esc_html__('Address of the actual seller. Used when printing invoices to display the address of the present seller of the product or trade name. For international orders',
                                                'cdekdelivery'),
                ],
                'shipper_name'                       => [
                    'title'       => esc_html__('Shipper', 'cdekdelivery'),
                    'type'        => 'text',
                    'desc_tip'    => true,
                    'description' => esc_html__('Shipper`s company name for international orders', 'cdekdelivery'),
                ],
                'shipper_address'                    => [
                    'title'       => esc_html__('Shipper`s address', 'cdekdelivery'),
                    'type'        => 'text',
                    'desc_tip'    => true,
                    'description' => esc_html__('Shipping company address for international orders', 'cdekdelivery'),
                ],
                'passport_series'                    => [
                    'title' => esc_html__('Passport Series', 'cdekdelivery'),
                    'type'  => 'text',
                ],
                'passport_number'                    => [
                    'title'             => esc_html__('Passport number', 'cdekdelivery'),
                    'type'              => 'text',
                    'custom_attributes' => [
                        'pattern' => '\d*',
                    ],
                ],
                'passport_date_of_issue'             => [
                    'title'       => esc_html__('Passport date of issue', 'cdekdelivery'),
                    'type'        => 'date',
                    'date_format' => 'd.m.Y',
                ],
                'passport_organization'              => [
                    'title' => esc_html__('Passport organization', 'cdekdelivery'),
                    'type'  => 'text',
                ],
                'tin'                                => [
                    'title'             => esc_html__('TIN', 'cdekdelivery'),
                    'type'              => 'text',
                    'desc_tip'          => true,
                    'custom_attributes' => [
                        'pattern' => '\d*',
                    ],
                ],
                'passport_date_of_birth'             => [
                    'title'       => esc_html__('Birthday', 'cdekdelivery'),
                    'type'        => 'date',
                    'date_format' => 'd.m.Y',
                ],
                'automate_block_name'                => [
                    'title' => '<h3 style="text-align: center;">'.esc_html__('Automation', 'cdekdelivery').'</h3>',
                    'type'  => 'title',
                    'class' => 'cdek_delivery_block_name',
                ],
                'automate_orders'                    => [
                    'title'       => esc_html__('Automatically create waybills in CDEK', 'cdekdelivery'),
                    'type'        => 'checkbox',
                    'description' => esc_html__('If you have information about the dimensions and correctly filled in shipping addresses, CDEK waybills will be created automatically',
                                                'cdekdelivery'),
                ],
                'automate_wait_gateways'             => [
                    'title'       => esc_html__('Wait for gateways', 'cdekdelivery'),
                    'type'        => 'multiselect',
                    'options'     => $availableGateways,
                    'description' => esc_html__('Plugin will wait for selected gateways to finish payments before auto-creation of waybill in CDEK. If order is working with non selected payment gateway, CDEK waybill will be created right after order placement',
                                                'cdekdelivery'),
                ],
                'delivery_block_name'                => [
                    'title' => '<h3 style="text-align: center;">'.esc_html__('Delivery', 'cdekdelivery').'</h3>',
                    'type'  => 'title',
                    'class' => 'cdek_delivery_block_name',
                ],
                'tariff_list'                        => [
                    'title'       => esc_html__('Tariff', 'cdekdelivery'),
                    'type'        => 'multiselect',
                    'desc_tip'    => true,
                    'options'     => Tariff::getTariffList(),
                    'description' => esc_html__('To select multiple tariffs, hold down the "CTRL" key and select tariffs with the left mouse button.',
                                                'cdekdelivery'),
                    'css'         => 'height: 400px;',
                ],
                'tariff_name'                        => [
                    'title'       => esc_html__('Change tariff name', 'cdekdelivery'),
                    'type'        => 'text',
                    'description' => sprintf(esc_html__('In the list of tariffs in the field "Tariffs" the tariff code is indicated in brackets.\n\r To change the name of the tariff, an entry in the code-name format is added to the field; for multiple changes,\n\r tariffs are separated by a semicolon, for example, an entry that will change the name of tariff 136 and 137 looks like this:%s If the value is not specified, the tariff names will be standard.',
                                                        'cdekdelivery'),
                                             '<b>136-Доставка до пвз;137-Доставка курьером</b> <br>'),
                ],
                'has_packages_mode'                  => [
                    'title'       => esc_html__('Multi-seater', 'cdekdelivery'),
                    'type'        => 'checkbox',
                    'desc_tip'    => true,
                    'description' => esc_html__('When the "Multi-seat" mode is enabled, the detailed order page will display the ability to create several packages for one order and distribute goods among the created packages',
                                                'cdekdelivery'),
                    'default'     => 'no',
                ],
                'extra_day'                          => [
                    'title'             => esc_html__('Add days for delivery', 'cdekdelivery'),
                    'type'              => 'number',
                    'desc_tip'          => true,
                    'description'       => esc_html__('Number of days will be added to the estimated delivery time',
                                                      'cdekdelivery'),
                    'default'           => 0,
                    'custom_attributes' => [
                        'min'  => 0,
                        'step' => 1,
                    ],
                ],
                'map_auto_close'                     => [
                    'title'       => esc_html__('Close the map after selecting pick-up', 'cdekdelivery'),
                    'type'        => 'checkbox',
                    'desc_tip'    => true,
                    'description' => esc_html__('If this setting is enabled, then after selecting a pick-up point on the checkout page, the card will automatically close.',
                                                'cdekdelivery'),
                    'default'     => 'no',
                ],
                'map'                                => [
                    'type'  => 'hidden',
                    'title' => esc_html__('Select addresses to send on the map', 'cdekdelivery'),
                ],
                'pvz_code'                           => [
                    'type' => 'hidden',
                ],
                'address'                            => [
                    'type' => 'hidden',
                ],
                'token'                              => [
                    'type' => 'hidden',
                ],
                'package_setting_block_name'         => [
                    'title' => '<h3 style="text-align: center;">'.esc_html__('Dimensions', 'cdekdelivery').'</h3>',
                    'type'  => 'title',
                    'class' => 'cdek_package_setting_block_name',
                ],
                'product_weight_default'             => [
                    'title'             => esc_html__('Default weight of one item in', 'cdekdelivery').
                                           ' ('.
                                           get_option('woocommerce_weight_unit').
                                           ')',
                    'desc_tip'          => true,
                    'description'       => sprintf(esc_html__('All goods must have their weight indicated, if there are goods without %s a specified weight, then for such goods the value from this field will be substituted. %s This will affect the accuracy of the delivery calculation. The default value is 1 weight unit specified in the settings.',
                                                              'cdekdelivery'), "<br>", "<br>"),
                    'type'              => 'number',
                    'default'           => 1,
                    'custom_attributes' => [
                        'min'  => 0,
                        'step' => 0.01,
                    ],
                ],
                'product_length_default'             => [
                    'title'             => esc_html__('Item length', 'cdekdelivery'),
                    'description'       => esc_html__('Default product length in cm', 'cdekdelivery'),
                    'type'              => 'number',
                    'desc_tip'          => true,
                    'default'           => 10,
                    'custom_attributes' => [
                        'min'  => 1,
                        'step' => 1,
                    ],
                ],
                'product_width_default'              => [
                    'title'             => esc_html__('Item width', 'cdekdelivery'),
                    'description'       => esc_html__('Default product width in cm', 'cdekdelivery'),
                    'type'              => 'number',
                    'desc_tip'          => true,
                    'default'           => 10,
                    'custom_attributes' => [
                        'min'  => 1,
                        'step' => 1,
                    ],
                ],
                'product_height_default'             => [
                    'title'             => esc_html__('Item height', 'cdekdelivery'),
                    'description'       => esc_html__('Default product height in cm', 'cdekdelivery'),
                    'type'              => 'number',
                    'desc_tip'          => true,
                    'default'           => 10,
                    'custom_attributes' => [
                        'min'  => 1,
                        'step' => 1,
                    ],
                ],
                'product_package_default_toggle'     => [
                    'title'       => esc_html__('Product dimensions on/off', 'cdekdelivery'),
                    'description' => esc_html__('Force the use of product dimensions (length, width and height) by default for all products',
                                                'cdekdelivery'),
                    'type'        => 'checkbox',
                    'desc_tip'    => true,
                    'default'     => 'no',
                ],
                'services_block_name'                => [
                    'title' => '<h3 style="text-align: center;">'.esc_html__('Services', 'cdekdelivery').'</h3>',
                    'type'  => 'title',
                    'class' => 'cdek_delivery_block_name',
                ],
                'services_ban_attachment_inspection' => [
                    'title'       => esc_html__('Prohibition of inspection of attachment', 'cdekdelivery'),
                    'description' => esc_html__('This service is not available for tariffs to the parcel locker and is only available to clients with an IM type agreement.\n\r Also, the prohibition on inspecting the attachment does not work when the services of fitting at home and partial delivery are included.',
                                                'cdekdelivery'),
                    'type'        => 'checkbox',
                    'default'     => 'no',
                ],
                'services_trying_on'                 => [
                    'title'       => esc_html__('Trying on', 'cdekdelivery'),
                    'description' => '',
                    'type'        => 'checkbox',
                    'default'     => 'no',
                ],
                'services_part_deliv'                => [
                    'title'       => esc_html__('Partial delivery', 'cdekdelivery'),
                    'description' => '',
                    'type'        => 'checkbox',
                    'default'     => 'no',
                ],
                'delivery_price_block_name'          => [
                    'title' => '<h3 style="text-align: center;">'.esc_html__('Delivery cost', 'cdekdelivery').'</h3>',
                    'type'  => 'title',
                    'class' => 'cdek_delivery_price_block_name',
                ],
                'insurance'                          => [
                    'title'       => esc_html__('Insurance', 'cdekdelivery'),
                    'label'       => esc_html__('Add insurance quote to shipping cost', 'cdekdelivery'),
                    'type'        => 'checkbox',
                    'desc_tip'    => true,
                    'description' => esc_html__('Calculated based on the amount of goods in the order', 'cdekdelivery'),
                ],
                'delivery_price_rules'               => [
                    'title' => esc_html__('Rules for displaying the delivery amount', 'cdekdelivery'),
                    'label' => '',
                    'type'  => 'hidden',
                ],
                'stepcodprice_title'                 => [
                    'title'       => esc_html__('Cash on delivery settings', 'cdekdelivery'),
                    'type'        => 'title',
                    'description' => esc_html__('Cash on delivery settings are applied only when sending an order from the admin panels and for the user on the checkout page are not displayed',
                                                'cdekdelivery'),
                ],
                'percentcod'                         => [
                    'title'             => esc_html__('Extra charge on order as a percentage', 'cdekdelivery'),
                    'type'              => 'number',
                    'description'       => sprintf(esc_html__('Calculated from the cost of the order. Changes the total amount on the receipt.%s The surcharge will only appear on the receipt.%s Therefore, it is recommended to inform the user on the checkout page about extra charges when sending by cash on delivery.',
                                                              'cdekdelivery'), "<br> <b> ", "</b> "),
                    'custom_attributes' => [
                        'min'  => 100,
                        'step' => 1,
                    ],
                ],
                'city_code_value'                    => [
                    'type'    => 'text',
                    'css'     => 'display: none;',
                    'default' => '44',
                ],
                'barcode_format_title'               => [
                    'title' => esc_html__('Print settings', 'cdekdelivery'),
                    'type'  => 'title',
                ],
                'barcode_format'                     => [
                    'title'   => esc_html__('Barcode format', 'cdekdelivery'),
                    'type'    => 'select',
                    'options' => BarcodeFormat::getAll(),
                ],
            ];
        }

        public function get_option($key, $empty_value = null)
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
            $option = apply_filters('woocommerce_shipping_'.$this->id.'_option',
                                    WC_Settings_API::get_option($key, $empty_value), $key, $this);

            return $option;
        }

        final public function calculate_shipping($package = []): void
        {
            try {
                $deliveryCalc = new CalculateDeliveryAction($this->get_instance_id());
                if (!$deliveryCalc($package)) {
                    return;
                }

                foreach ($deliveryCalc->getRates() as $rate) {
                    $this->add_rate($rate);
                }
            } catch (CdekException $e) {
                return;
            }
        }
    }
}
