<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

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
    }

}
