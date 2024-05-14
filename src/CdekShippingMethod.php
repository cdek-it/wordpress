<?php

namespace Cdek;

use Cdek\Enums\BarcodeFormat;
use Cdek\Helpers\DeliveryCalc;
use Cdek\Model\Tariff;
use WC_Settings_API;
use WC_Shipping_Method;

class CdekShippingMethod extends WC_Shipping_Method
{
    public function __construct($instance_id = 0)
    {
        parent::__construct($instance_id);
        $this->id                 = Config::DELIVERY_NAME;
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Cdek Shipping', 'cdekdelivery');
        $this->method_description = __('Official Shipping Method for Cdek', 'cdekdelivery');
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
        $this->title = __('CDEK Shipping', 'cdekdelivery');
        $this->init_settings();
        add_action('woocommerce_update_options_shipping_'.$this->id, [$this, 'process_admin_options']);
        $this->init_form_fields();
    }

    final public function init_form_fields(): void
    {
        $this->instance_form_fields = [
            'use_delivery_price_rules' => [
                'title' => __('Use delivery amount display rules for a zone', 'cdekdelivery'),
                'type'  => 'checkbox',
            ],
            'delivery_price_rules'     => [
                'title' => __('Rules for displaying the delivery amount', 'cdekdelivery'),
                'label' => '',
                'type'  => 'hidden',
            ],
        ];

        $this->form_fields = [
            'auth_block_name'                => [
                'title' => '<h3 style="text-align: center;">' .
                           __('Authorization', 'cdekdelivery') .
                           '</h3>',
                'type'  => 'title',
                'class' => 'cdek_setting_block_name',
            ],
            'test_mode'                      => [
                'title'       => __('Test mode', 'cdekdelivery'),
                'type'        => 'checkbox',
                'default'     => false,
                'desc_tip'    => true,
                'description' => __('When enabled, the test API is used', 'cdekdelivery'),
            ],
            'client_id'                      => [
                'title'             => __('Identifier', 'cdekdelivery'),
                'type'              => 'text',
                'custom_attributes' => [
                    'required' => true,
                ],
            ],
            'client_secret'                  => [
                'title'             => __('The secret key', 'cdekdelivery'),
                'type'              => 'text',
                'custom_attributes' => [
                    'required' => true,
                ],
            ],
            'yandex_map_api_key'             => [
                'type'              => 'text',
                'title'             => __('Yandex map key', 'cdekdelivery'),
                'description'       => str_replace(
                    '<a>',
                    '<a rel="noopener nofollower" href="https://yandex.ru/dev/jsapi-v2-1/doc/ru/#get-api-key" target="_blank">',
                    __('Yandex API access key. The generation process is described on <a>the page</a>', 'cdekdelivery')
                ),
                'custom_attributes' => [
                    'required' => true,
                ],
            ],
            'seller_block_name'              => [
                'title' => '<h3 style="text-align: center;">' . __('Client', 'cdekdelivery') . '</h3>',
                'type'  => 'title',
                'class' => 'cdek_setting_block_name',
            ],
            'seller_company'                 => [
                'title'             => __('Company name', 'cdekdelivery'),
                'type'              => 'text',
                'custom_attributes' => [
                    'required' => true,
                ],
            ],
            'seller_name'                    => [
                'title'             => __('FIO', 'cdekdelivery'),
                'type'              => 'text',
                'custom_attributes' => [
                    'required' => true,
                ],
            ],
            'seller_phone'                   => [
                'title'             => __('Phone', 'cdekdelivery'),
                'type'              => 'text',
                'desc_tip'          => true,
                'description'       => __('Must be transmitted in international format: country code (for Russia +7) and the number itself (10 or more digits)', 'cdekdelivery'),
                'custom_attributes' => [
                    'required' => true,
                ],
            ],
            'seller_email'                   => [
                'title'             => __('Company email address', 'cdekdelivery'),
                'type'              => 'text',
                'custom_attributes' => [
                    'required' => true,
                    'type'     => 'email',
                ],
            ],
            'international_title'            => [
                'title' => __('International orders', 'cdekdelivery'),
                'type'  => 'title',
            ],
            'international_mode'             => [
                'title'       => __('Enable international order mode', 'cdekdelivery'),
                'type'        => 'checkbox',
                'desc_tip'    => true,
                'description' => __("When the international orders mode is enabled, additional fields will appear on the checkout page:\n\r passport series, passport number, date of issue, department, TIN, date of birth.", 'cdekdelivery'),
                'default'     => 'no',
            ],
            'seller_address'                 => [
                'title'       => __('True seller address', 'cdekdelivery'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __("Address of the actual seller. Used when printing invoices to display the address of the present\n\r seller of the product or trade name. For international orders", 'cdekdelivery'),
            ],
            'shipper_name'                   => [
                'title'       =>  __('Shipper', 'cdekdelivery'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('Shipper`s company name for international orders', 'cdekdelivery'),
            ],
            'shipper_address'                => [
                'title'       => __('Shipper`s address', 'cdekdelivery'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __('Shipping company address for international orders', 'cdekdelivery'),
            ],
            'passport_series'                => [
                'title' => __('Passport Series', 'cdekdelivery'),
                'type'  => 'text',
            ],
            'passport_number'                => [
                'title'             => __('Passport number', 'cdekdelivery'),
                'type'              => 'text',
                'custom_attributes' => [
                    'pattern' => '\d*',
                ],
            ],
            'passport_date_of_issue'         => [
                'title'       => __('Passport date of issue', 'cdekdelivery'),
                'type'        => 'date',
                'date_format' => 'd.m.Y',
            ],
            'passport_organization'          => [
                'title' => __('Passport organization', 'cdekdelivery'),
                'type'  => 'text',
            ],
            'tin'                            => [
                'title'             => __('TIN', 'cdekdelivery'),
                'type'              => 'text',
                'desc_tip'          => true,
                'custom_attributes' => [
                    'pattern' => '\d*',
                ],
            ],
            'passport_date_of_birth'         => [
                'title'       => __('Birthday', 'cdekdelivery'),
                'type'        => 'date',
                'date_format' => 'd.m.Y',
            ],
            'delivery_block_name'            => [
                'title' => '<h3 style="text-align: center;">' . __('Delivery', 'cdekdelivery') .'</h3>',
                'type'  => 'title',
                'class' => 'cdek_delivery_block_name',
            ],
            'automate_orders'                => [
                'title'       => __('Automatically create orders in CDEK', 'cdekdelivery'),
                'type'        => 'checkbox',
                'description' => __('If you have information about the dimensions and correctly filled in shipping addresses, the CDEK invoice will be created automatically', 'cdekdelivery'),
            ],
            'tariff_list'                    => [
                'title'       => __('Tariff', 'cdekdelivery'),
                'type'        => 'multiselect',
                'desc_tip'    => true,
                'options'     => Tariff::getTariffList(),
                'description' => __("To select multiple tariffs, hold down the \"CTRL\" key and select tariffs with the left mouse button.", 'cdekdelivery'),
                'css'         => 'height: 400px;',
            ],
            'tariff_name'                    => [
                'title'       => __('Change tariff name', 'cdekdelivery'),
                'type'        => 'text',
                'description' => sprintf(
                    __(
                        "In the list of tariffs in the field \"Tariffs\" the tariff code is indicated in brackets.\n\r To change the name of the tariff, an entry in the code-name format is added to the field; for multiple changes,\n\r tariffs are separated by a semicolon, for example, an entry that will change the name of tariff 136 and 137 looks like this:%s If the value is not specified, the tariff names will be standard.",
                        'cdekdelivery'),
                    '<b>136-Доставка до пвз;137-Доставка курьером</b> <br>'),
            ],
            'has_packages_mode'              => [
                'title'       => __('Multi-seater', 'cdekdelivery'),
                'type'        => 'checkbox',
                'desc_tip'    => true,
                'description' => __("When the 'Multi-seat' mode is enabled, the detailed order page will display\n\r the ability to create several packages for one order and distribute goods among the created packages", 'cdekdelivery'),
                'default'     => 'no',
            ],
            'extra_day'                      => [
                'title'             => __('Add days for delivery', 'cdekdelivery'),
                'type'              => 'number',
                'desc_tip'          => true,
                'description'       => __('Number of days will be added to the estimated delivery time', 'cdekdelivery'),
                'default'           => 0,
                'custom_attributes' => [
                    'min'  => 0,
                    'step' => 1,
                ],
            ],
            'map_auto_close'              => [
                'title'       => __('Close the map after selecting pick-up', 'cdekdelivery'),
                'type'        => 'checkbox',
                'desc_tip'    => true,
                'description' => __('If this setting is enabled, then after selecting a pick-up point on the checkout page, the card will automatically close.', 'cdekdelivery'),
                'default'     => 'no',
            ],
            'map'                            => [
                'type'  => 'hidden',
                'title' => __('Select addresses to send on the map', 'cdekdelivery'),
            ],
            'pvz_code'                       => [
                'type' => 'hidden',
            ],
            'address'                        => [
                'type' => 'hidden',
            ],
            'token'                          => [
                'type' => 'hidden',
            ],
            'package_setting_block_name'     => [
                'title' => '<h3 style="text-align: center;">' . __('Dimensions', 'cdekdelivery') .'</h3>',
                'type'  => 'title',
                'class' => 'cdek_package_setting_block_name',
            ],
            'product_weight_default'         => [
                'title'             => __('Default weight of one item in', 'cdekdelivery') . ' ('.
                                       get_option('woocommerce_weight_unit').
                                       ')',
                'desc_tip'          => true,
                'description'       => sprintf(__('All goods must have their weight indicated, if there are goods without %s a specified weight, then for such goods the value from this field will be substituted. %s This will affect the accuracy of the delivery calculation. The default value is 1 weight unit specified in the settings.', 'cdekdelivery'),
                                       "<br>", "<br>"),
                'type'              => 'number',
                'default'           => 1,
                'custom_attributes' => [
                    'min'  => 0,
                    'step' => 0.01,
                ],
            ],
            'product_length_default'         => [
                'title'             =>  __('Item length', 'cdekdelivery'),
                'description'       =>  __('Default product length in cm', 'cdekdelivery'),
                'type'              => 'number',
                'desc_tip'          => true,
                'default'           => 10,
                'custom_attributes' => [
                    'min'  => 1,
                    'step' => 1,
                ],
            ],
            'product_width_default'          => [
                'title'             => __('Item width', 'cdekdelivery'),
                'description'       => __('Default product width in cm', 'cdekdelivery'),
                'type'              => 'number',
                'desc_tip'          => true,
                'default'           => 10,
                'custom_attributes' => [
                    'min'  => 1,
                    'step' => 1,
                ],
            ],
            'product_height_default'         => [
                'title'             => __('Item height', 'cdekdelivery'),
                'description'       => __('Default product height in cm', 'cdekdelivery'),
                'type'              => 'number',
                'desc_tip'          => true,
                'default'           => 10,
                'custom_attributes' => [
                    'min'  => 1,
                    'step' => 1,
                ],
            ],
            'product_package_default_toggle' => [
                'title'       => __('Product dimensions on/off', 'cdekdelivery'),
                'description' => __('Force the use of product dimensions (length, width and height) by default for all products', 'cdekdelivery'),
                'type'        => 'checkbox',
                'desc_tip'    => true,
                'default'     => 'no',
            ],
            'services_block_name'            => [
                'title' => '<h3 style="text-align: center;">'. __('Services', 'cdekdelivery') .'</h3>',
                'type'  => 'title',
                'class' => 'cdek_delivery_block_name',
            ],
            'services_ban_attachment_inspection' => [
                'title'       => __('Prohibition of inspection of attachment', 'cdekdelivery'),
                'description' => __("This service is not available for tariffs to the parcel locker and is only available to clients with an IM type agreement.\n\r Also, the prohibition on inspecting the attachment does not work when the services of fitting at home and partial delivery are included.", 'cdekdelivery'),
                'type'        => 'checkbox',
                'default'     => 'no',
            ],
            'services_trying_on' => [
                'title'       => __('Trying on', 'cdekdelivery'),
                'description' => '',
                'type'        => 'checkbox',
                'default'     => 'no',
            ],
            'services_part_deliv' => [
                'title'       => __('Partial delivery', 'cdekdelivery'),
                'description' => '',
                'type'        => 'checkbox',
                'default'     => 'no',
            ],
            'delivery_price_block_name'      => [
                'title' => '<h3 style="text-align: center;">' . __('Delivery cost', 'cdekdelivery') . '</h3>',
                'type'  => 'title',
                'class' => 'cdek_delivery_price_block_name',
            ],
            'insurance'                      => [
                'title'       => __('Insurance', 'cdekdelivery'),
                'label'       => __('Add insurance quote to shipping cost', 'cdekdelivery'),
                'type'        => 'checkbox',
                'desc_tip'    => true,
                'description' => __('Calculated based on the amount of goods in the order', 'cdekdelivery'),
            ],
            'delivery_price_rules'           => [
                'title' => __('Rules for displaying the delivery amount', 'cdekdelivery'),
                'label' => '',
                'type'  => 'hidden',
            ],
            'stepcodprice_title'             => [
                'title'       => __('Cash on delivery settings', 'cdekdelivery'),
                'type'        => 'title',
                'description' => __("Cash on delivery settings are applied only when sending an order from the admin\n\r panels and for the user on the checkout page are not displayed", 'cdekdelivery'),
            ],
            'percentcod'                     => [
                'title'             => __('Extra charge on order as a percentage', 'cdekdelivery'),
                'type'              => 'number',
                'description'       => sprintf(
                    __("Calculated from the cost of the order.\n\r Changes the total amount on the receipt.%s The surcharge will only appear on the receipt.%s Therefore, it is recommended to inform the user on the checkout page\n\r about extra charges when sending by cash on delivery.", 'cdekdelivery'),
                "<br> <b> ", "</b> "),
                'custom_attributes' => [
                    'min'  => 100,
                    'step' => 1,
                ],
            ],
            'city_code_value'                => [
                'type'    => 'text',
                'css'     => 'display: none;',
                'default' => '44',
            ],
            'barcode_format_title'           => [
                'title' => __('Print settings', 'cdekdelivery'),
                'type'  => 'title',
            ],
            'barcode_format'                 => [
                'title'   => __('Barcode format', 'cdekdelivery'),
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

    public function calculate_shipping($package = []): void
    {
        $deliveryCalc = new DeliveryCalc($this->get_instance_id());
        if (!$deliveryCalc->calculate($package)) {
            return;
        }

        foreach ($deliveryCalc->getRates() as $rate) {
            $this->add_rate($rate);
        }
    }
}
