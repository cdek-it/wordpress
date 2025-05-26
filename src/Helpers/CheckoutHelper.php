<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\Config;
    use Cdek\Contracts\FieldsetContract;
    use Cdek\Fieldsets\GeneralOrderFields;
    use Cdek\Fieldsets\InternationalOrderFields;
    use Cdek\MetaKeys;
    use Throwable;
    use WC_Cart;
    use WC_Shipping_Rate;

    class CheckoutHelper
    {
        private const AVAILABLE_FIELDSETS
            = [
                GeneralOrderFields::class,
                InternationalOrderFields::class,
            ];

        /** @noinspection GlobalVariableUsageInspection */
        public static function getCurrentValue(string $valueName, string $defaultValue = null): ?string
        {
            try {
                $cdekValue = WC()->session->get(Config::DELIVERY_NAME."_$valueName");
                if (!empty($cdekValue)) {
                    return $cdekValue;
                }
            } catch (Throwable $e) {
                //do nothing
            }

            $checkout = WC()->checkout();

            $shippingValue = $checkout->get_value("shipping_$valueName");
            if (!empty($shippingValue)) {
                return $shippingValue;
            }

            $billingValue = $checkout->get_value("billing_$valueName");
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

            return $checkout->get_value($valueName) ?: $defaultValue;
        }

        public static function passOfficeToCartPackages(array $packages): array {
            return array_map(
                static function (array $package) {
                    $office = CheckoutHelper::getCurrentValue('office_code');

                    if (!empty($office)) {
                        $package['destination'][MetaKeys::OFFICE_CODE] = $office;
                    }

                    return $package;
                },
                $packages,
            );
        }

        public static function getSelectedShippingRate(?WC_Cart $cart = null): ?WC_Shipping_Rate
        {
            if (is_null($cart)) {
                $cart = WC()->cart;
            }

            if (is_null($cart)) {
                return null;
            }

            $methods = $cart->get_shipping_methods();

            if (empty($methods)) {
                $methods = $cart->calculate_shipping();
            }

            if (is_null($methods)){
                return null;
            }

            foreach ($methods as $method) {
                assert($method instanceof WC_Shipping_Rate);
                if (self::isShippingRateSuitable($method)) {
                    return $method;
                }
            }

            return null;
        }

        public static function isShippingRateSuitable(WC_Shipping_Rate $rate): bool
        {
            return $rate->get_method_id() === Config::DELIVERY_NAME;
        }

        public static function restoreFields(array $fields): array
        {
            if (self::getSelectedShippingRate() === null) {
                return $fields;
            }

            $originalFields = WC()->checkout()->get_checkout_fields('billing');

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
    }
}
