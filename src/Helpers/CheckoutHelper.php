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
    use Throwable;

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

        public static function restoreFields(array $fields): array
        {
            $shippingDetector = ShippingDetector::new();

            if (
                !$shippingDetector->needShipping() ||
                (
                    !$shippingDetector->isShippingEmpty() &&
                    $shippingDetector->getShipping() === null
                )
            ) {
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
