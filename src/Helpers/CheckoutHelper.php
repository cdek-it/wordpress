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

        public static function passOfficeToCartPackages(array $packages): array
        {
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

        public static function getCurrentValue(string $valueName, string $defaultValue = null): ?string
        {
            try {
                $cdekValue = WC()->session->get(Config::DELIVERY_NAME . "_$valueName");
                if (!empty($cdekValue)) {
                    return $cdekValue;
                }
            } catch (Throwable $e) {
                //do nothing
            }

            $checkout = WC()->checkout();

            // При "Принудительная доставка по платёжному адресу клиента" billing_* приоритетнее.
            $primaryField   = wc_ship_to_billing_address_only() ? "billing_$valueName" : "shipping_$valueName";
            $secondaryField = wc_ship_to_billing_address_only() ? "shipping_$valueName" : "billing_$valueName";

            $primaryValue = $checkout->get_value($primaryField);
            if (!empty($primaryValue)) {
                return $primaryValue;
            }

            $secondaryValue = $checkout->get_value($secondaryField);
            if (!empty($secondaryValue)) {
                return $secondaryValue;
            }

            if (!empty($_REQUEST['extensions'][Config::DELIVERY_NAME][$valueName])) {
                return wp_strip_all_tags(wp_unslash($_REQUEST['extensions'][Config::DELIVERY_NAME][$valueName]));
            }

            if (!empty($_REQUEST[$valueName])) {
                return wp_strip_all_tags(wp_unslash($_REQUEST[$valueName]));
            }

            try {
                $cdekValue = WC()->customer->get_meta(Config::DELIVERY_NAME . "_$valueName");

                if (!empty($cdekValue)) {
                    return $cdekValue;
                }
            } catch (Throwable $e) {
                //do nothing
            }

            return $checkout->get_value($valueName) ?: $defaultValue;
        }

        public static function restoreFields(array $fields): array
        {
            $hasCdekRate = self::getSelectedShippingRate() !== null;

            if (!$hasCdekRate && !(new InternationalOrderFields)->isModeEnabled()) {
                return $fields;
            }

            $originalFields = WC()->checkout()->get_checkout_fields('billing');

            foreach (self::AVAILABLE_FIELDSETS as $fieldset) {
                $fieldsetInstance = new $fieldset;

                assert($fieldsetInstance instanceof FieldsetContract);

                $isInternational = $fieldsetInstance instanceof InternationalOrderFields;

                if ($isInternational ? !$fieldsetInstance->isModeEnabled() : !$fieldsetInstance->isApplicable()) {
                    continue;
                }

                // Скрываем при доставке по РФ и когда нет тарифа СДЭК (нет тарифа — паспортные данные не нужны)
                $hideInternational = $isInternational && ($fieldsetInstance->isDomestic() || !$hasCdekRate);

                if (!$hasCdekRate && !$isInternational) {
                    continue;
                }

                foreach ($fieldsetInstance->getFieldsNames() as $field) {
                    if (empty($fields['billing'][$field])) {
                        $fields['billing'][$field] = empty($originalFields[$field]) ?
                            $fieldsetInstance->getFieldDefinition($field) : $originalFields[$field];
                    }

                    if ($isInternational) {
                        $fields['billing'][$field]['class'][] = 'cdek-international-field';

                        if ($hideInternational) {
                            $fields['billing'][$field]['class'][] = 'cdek-international-field-hidden';
                        }
                    }

                    if ($fieldsetInstance->isRequiredField($field) && !$hideInternational) {
                        $fields['billing'][$field]['required'] = true;
                    } elseif ($isInternational) {
                        $fields['billing'][$field]['required'] = false;
                    }
                }
            }

            return $fields;
        }

        public static function getSelectedShippingRate(?WC_Cart $cart = null): ?WC_Shipping_Rate
        {
            if (is_null($cart)) {
                $cart = WC()->cart;
            }

            if (is_null($cart)) {
                return null;
            }

            if (method_exists($cart, 'get_shipping_methods')) {
                $methods = $cart->get_shipping_methods();
            } else {
                $methods = [];
            }

            if (empty($methods)) {
                $methods = $cart->calculate_shipping();
            }

            if (is_null($methods)) {
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

        /** @noinspection GlobalVariableUsageInspection */
        public static function isCheckoutRequest(): bool
        {
            // Классический (shortcode) чекаут отправляется обычным POST/AJAX на страницу
            // чекаута (wc-ajax=checkout), а не на Store API - строковые проверки ниже его
            // не ловят, поэтому нужен отдельный WC-условный тег.
            if (function_exists('is_checkout') && is_checkout()) {
                return true;
            }

            $restRoute = isset($_GET['rest_route']) ? wp_unslash((string)$_GET['rest_route']) : '';
            $uri       = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';

            return strpos($restRoute, '/wc/store/v1/checkout') === 0
                || strpos($uri, '/wc/store/v1/checkout') !== false;
        }
    }
}
