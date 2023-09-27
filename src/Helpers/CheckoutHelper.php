<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\Config;
    use RuntimeException;
    use WC_Order;

    class CheckoutHelper {
        public static function getValueFromCurrentSession(string $valueName, $defaultValue = null) {
            $shippingValue = WC()->checkout()->get_value("shipping_$valueName");
            if (isset($shippingValue)) {
                return $shippingValue;
            }

            $billingValue = WC()->checkout()->get_value("billing_$valueName");

            if (isset($billingValue)) {
                return $billingValue;
            }

            $rawValue = WC()->checkout()->get_value($valueName);

            return $rawValue ?? $defaultValue;
        }

        public static function isCdekShippingMethod(WC_Order $order): bool {
            try {
                return self::getOrderShippingMethod($order)->get_method_id() === Config::DELIVERY_NAME;
            } catch (RuntimeException $e) {
                return false;
            }
        }

        public static function getOrderShippingMethod(WC_Order $order) {
            $shippingMethodArray = $order->get_items('shipping');
            if (empty($shippingMethodArray)) {
                throw new RuntimeException('Order don\'t have shipping methods');
            }

            return array_shift($shippingMethodArray);
        }
    }
}
