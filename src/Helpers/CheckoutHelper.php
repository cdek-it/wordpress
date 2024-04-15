<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\Config;
    use Cdek\Helper;
    use RuntimeException;
    use Throwable;
    use WC_Order;
    use WC_Order_Item;

    class CheckoutHelper
    {
        public static function getValueFromCurrentSession(string $valueName, string $defaultValue = null): ?string
        {
            try {
                $cdekValue = WC()->session->get(Config::DELIVERY_NAME."_$valueName");
                if (!empty($cdekValue)) {
                    return $cdekValue;
                }
            } catch (Throwable $e) {
                //do nothing
            }

            $shippingValue = WC()->checkout()->get_value("shipping_$valueName");
            if (!empty($shippingValue)) {
                return $shippingValue;
            }

            $billingValue = WC()->checkout()->get_value("billing_$valueName");
            if (!empty($billingValue)) {
                return $billingValue;
            }

            if (!empty($_REQUEST['extensions'][Config::DELIVERY_NAME][$valueName])) {
                return $_REQUEST['extensions'][Config::DELIVERY_NAME][$valueName];
            }

            if (!empty($_REQUEST[$valueName])) {
                return $_REQUEST[$valueName];
            }

            return WC()->checkout()->get_value($valueName) ?: $defaultValue;
        }

        public static function isCdekShippingMethod(WC_Order $order): bool
        {
            try {
                return self::getOrderShippingMethod($order)->get_method_id() === Config::DELIVERY_NAME;
            } catch (RuntimeException $e) {
                return false;
            }
        }

        public static function getOrderShippingMethod(WC_Order $order): WC_Order_Item
        {
            $shippingMethodArray = $order->get_items('shipping');
            if (empty($shippingMethodArray)) {
                throw new RuntimeException('Order don\'t have shipping methods');
            }

            return array_shift($shippingMethodArray);
        }

        public static function restoreCheckoutFields(array $fields): array
        {
            $checkout = WC()->checkout();

            $originalFields = $checkout->get_checkout_fields('billing');

            //Восстанавливаем требуемые поля для чекаута
            foreach (
                [
                    'billing_first_name',
                    'billing_city',
                    'billing_phone',
                    'billing_address_1',
                ] as $requiredField
            ) {
                $fields['billing'][$requiredField] = $fields['billing'][$requiredField]
                                                     ??
                                                     $originalFields[$requiredField];
            }

            foreach (
                [
                    'billing_address_1'  => false,
                    'billing_address_2'  => false,
                    'billing_phone'      => true,
                    'billing_city'       => true,
                    'billing_first_name' => true,
                ] as $field => $value
            ) {
                if (isset($fields['billing'][$field])) {
                    $fields['billing'][$field]['required'] = $value;
                }
            }

            if (Helper::getActualShippingMethod()->get_option('international_mode') === 'yes') {
                $fields['billing']['passport_series']        = [
                    'label'             => __('Серия паспорта', 'woocommerce'),
                    'required'          => true,
                    'class'             => ['form-row-wide'],
                    'clear'             => true,
                    'priority'          => 120,
                    'custom_attributes' => [
                        'maxlength' => 4,
                    ],
                ];
                $fields['billing']['passport_number']        = [
                    'label'             => __('Номер паспорта', 'woocommerce'),
                    'required'          => true,
                    'class'             => ['form-row-wide'],
                    'clear'             => true,
                    'priority'          => 120,
                    'custom_attributes' => [
                        'maxlength' => 6,
                    ],
                ];
                $fields['billing']['passport_date_of_issue'] = [
                    'type'     => 'date',
                    'label'    => __('Дата выдачи паспорта', 'woocommerce'),
                    'required' => true,
                    'priority' => 120,
                    'class'    => ['form-row-wide'],
                    'clear'    => true,
                ];
                $fields['billing']['passport_organization']  = [
                    'label'    => __('Орган выдачи паспорта', 'woocommerce'),
                    'required' => true,
                    'priority' => 120,
                    'class'    => ['form-row-wide'],
                    'clear'    => true,
                ];
                $fields['billing']['tin']                    = [
                    'label'             => __('ИНН', 'woocommerce'),
                    'required'          => true,
                    'priority'          => 120,
                    'class'             => ['form-row-wide'],
                    'clear'             => true,
                    'custom_attributes' => [
                        'maxlength' => 12,
                    ],
                ];
                $fields['billing']['passport_date_of_birth'] = [
                    'type'     => 'date',
                    'priority' => 120,
                    'label'    => __('Дата рождения', 'woocommerce'),
                    'required' => true,
                    'class'    => ['form-row-wide'],
                    'clear'    => true,
                ];
            }

            return $fields;
        }

        public static function getMapAutoClose(): bool
        {
            return Helper::getActualShippingMethod()->get_option('map_auto_close') === 'yes';
        }
    }
}
