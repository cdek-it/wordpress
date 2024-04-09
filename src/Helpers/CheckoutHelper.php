<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\Checkout\GeneralOrderFields;
    use Cdek\Checkout\InternationalOrderFields;
    use Cdek\Checkout\VirtualOrderFields;
    use Cdek\Config;
    use Cdek\Helper;
    use Cdek\Checkout\FieldConstructorInterface;
    use Cdek\Exceptions\ShippingMethodNotFoundException;
    use Throwable;
    use WC_Order;
    use WC_Order_Item;

    class CheckoutHelper
    {

        const EXCLUDE_FIELD_VIRTUAL_BASKET = [
            'billing_city',
            'billing_state',
            'billing_address_1',
            'billing_address_2',
            'billing_postcode',
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
            } catch (ShippingMethodNotFoundException $e) {
                return false;
            }
        }

        public static function getOrderShippingMethod(WC_Order $order): WC_Order_Item
        {
            $shippingMethodArray = $order->get_items('shipping');
            if (empty($shippingMethodArray)) {
                throw new ShippingMethodNotFoundException('Order don\'t have shipping methods');
            }

            return array_shift($shippingMethodArray);
        }

        public static function restoreCheckoutFields(array $fields): array
        {
            $checkout = WC()->checkout();

            $originalFields = $checkout->get_checkout_fields('billing');

            $fieldsConstructor = self::getFieldConstructor($fields);

            //Восстанавливаем требуемые поля для чекаута
            foreach (
                $fieldsConstructor->getRequiredFields() as $requiredField
            ) {
                $fields['billing'][$requiredField] = $fields['billing'][$requiredField]
                                                     ??
                                                     $originalFields[$requiredField];
            }

            foreach ($fieldsConstructor->getFields() as $field => $value) {
                if (isset($fields['billing'][$field])) {
                    $fields['billing'][$field]['required'] = $value;
                }
            }

            foreach (array_keys($fields['billing']) as $field){
                if(!$fieldsConstructor->isExistField($field)){
                    unset($fields['billing'][$field]);
                }
            }

            if (Helper::getActualShippingMethod()->get_option('international_mode') === 'yes') {
                $fields['billing'] = array_merge($fields['billing'], (new InternationalOrderFields())->getFields());
            }

            return $fields;
        }

        public static function getMapAutoClose(): bool
        {
            return Helper::getActualShippingMethod()->get_option('map_auto_close') === 'yes';
        }

        public static function getFieldConstructor($fields): FieldConstructorInterface
        {
            $basketItems = WC()->cart->cart_contents;

            foreach ($basketItems as $basketItem){
                if(!$basketItem['data']->get_virtual()){
                    return new GeneralOrderFields($fields);
                }
            }

            return new VirtualOrderFields();
        }
    }
}
