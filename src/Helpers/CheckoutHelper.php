<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\Config;
    use Cdek\Contracts\FieldsetContract;
    use Cdek\Exceptions\ShippingMethodNotFoundException;
    use Cdek\Fieldsets\GeneralOrderFields;
    use Cdek\Fieldsets\InternationalOrderFields;
    use Cdek\Helper;
    use Throwable;
    use WC_Order;
    use WC_Order_Item;
    use WC_Order_Item_Shipping;

    class CheckoutHelper
    {
        private const AVAILABLE_FIELDSETS
            = [
                GeneralOrderFields::class,
                InternationalOrderFields::class,
            ];

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
                return wp_strip_all_tags($_REQUEST['extensions'][Config::DELIVERY_NAME][$valueName]);
            }

            if (!empty($_REQUEST[$valueName])) {
                return wp_strip_all_tags($_REQUEST[$valueName]);
            }

            try {
                $cdekValue = WC()->customer->get_meta(Config::DELIVERY_NAME."_$valueName");

                if (!empty($cdekValue)) {
                    return $cdekValue;
                }
            } catch (Throwable $e) {
                //do nothing
            }

            return WC()->checkout()->get_value($valueName) ?: $defaultValue;
        }

        public static function isCdekShippingMethod(WC_Order $order): bool
        {
            try {
                return self::getOrderShippingMethod($order)->get_method_id() === Config::DELIVERY_NAME;
            } catch (ShippingMethodNotFoundException $e) {
                return false;
            }
        }

        public static function getOrderShippingMethod(WC_Order $order): WC_Order_Item_Shipping
        {
            $shippingMethodArray = $order->get_shipping_methods();
            if (empty($shippingMethodArray)) {
                throw new ShippingMethodNotFoundException('Order don\'t have shipping methods');
            }

            $method = array_shift($shippingMethodArray);

            assert($method instanceof WC_Order_Item_Shipping);

            return $method;
        }

        public static function restoreCheckoutFields(array $fields): array
        {
            if(!empty(WC()->cart) && !WC()->cart->needs_shipping()){
                return $fields;
            }

            $checkout = WC()->checkout();

            $originalFields = $checkout->get_checkout_fields('billing');

            foreach (self::AVAILABLE_FIELDSETS as $fieldset) {
                $fieldsetInstance = new $fieldset;

                assert($fieldsetInstance instanceof FieldsetContract);

                if (!$fieldsetInstance->isApplicable()) {
                    continue;
                }

                foreach ($fieldsetInstance->getFieldsNames() as $field) {
                    if (empty($fields['billing'][$field])) {
                        $fields['billing'][$field] = empty($originalFields[$field]) ?
                            $fieldsetInstance->getFieldDefinition($field) : $originalFields[$field];
                    }

                    if ($fieldsetInstance->isRequiredField($field)) {
                        $fields['billing'][$field]['required'] = true;
                    }
                }
            }

            return $fields;
        }

        public static function getMapAutoClose(): bool
        {
            return Helper::getActualShippingMethod()->get_option('map_auto_close') === 'yes';
        }
    }
}
