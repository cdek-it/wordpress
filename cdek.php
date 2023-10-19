<?php
/**
 * Plugin Name: CDEKDelivery
 * Plugin URI: https://www.cdek.ru/ru/integration/modules/33
 * Description: Интеграция доставки CDEK
 * Version: dev
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: CDEK IT
 * WC requires at least: 6.9
 * WC tested up to: 8.0
 */

use Cdek\CdekApi;
use Cdek\Config;
use Cdek\Helper;
use Cdek\Helpers\CheckoutHelper;
use Cdek\Helpers\StringHelper;
use Cdek\Helpers\WeightCalc;
use Cdek\Loader;
use Cdek\Model\OrderMetaData;
use Cdek\Model\Tariff;

function_exists('add_action') or exit();

defined('ABSPATH') or exit;

if (file_exists(__DIR__.'/vendor/autoload.php')) {
    require __DIR__.'/vendor/autoload.php';
}

if (!class_exists(Loader::class)) {
    trigger_error('CDEKDelivery not fully installed! Please install with Composer or download full release archive.',
        E_USER_ERROR);
}

(new Loader)(__FILE__);

add_filter('woocommerce_new_order', 'cdek_woocommerce_new_order_action', 10, 2);
add_action('woocommerce_after_shipping_rate', 'cdek_map_display', 10, 2);
add_filter('woocommerce_checkout_fields', 'cdek_checkout_fields', 1090);
add_action('woocommerce_checkout_create_order', 'cdek_save_custom_checkout_field_to_order', 10, 2);

function getCityCode($city_code, $order) {
    $api      = new CdekApi();
    $cityCode = $city_code;
    if (empty($cityCode)) {
        $cityCode = $api->getCityCodeByCityName($order->get_shipping_city(), $order->get_shipping_state());
    }

    return $cityCode;
}

function setPackage($data, $orderId, $currency, $tariffType = Tariff::SHOP_TYPE) {
    $param = [];
    if (Helper::getActualShippingMethod()->get_option('has_packages_mode') === 'yes') {
        $packageData       = json_decode($data->get_param('package_data'));
        $param['packages'] = get_packages($orderId, $packageData, $currency);
    } else {
        $length      = $data->get_param('package_length');
        $width       = $data->get_param('package_width');
        $height      = $data->get_param('package_height');
        $order       = wc_get_order($orderId);
        $items       = $order->get_items();
        $itemsData   = [];
        $totalWeight = 0;
        foreach ($items as $key => $item) {
            $product     = $item->get_product();
            $weight      = $product->get_weight();
            $weightClass = new WeightCalc();
            $weight      = $weightClass->getWeight($weight);
            $quantity    = (int) $item->get_quantity();
            $totalWeight += $quantity * $weight;
            $cost        = $product->get_price();

            if ($currency !== 'RUB' && function_exists('wcml_get_woocommerce_currency_option')) {
                $cost = convert_currency_cost_to_rub($cost, $currency);
            }

            $selectedPaymentMethodId = $order->get_payment_method();
            $percentCod              = (int) Helper::getActualShippingMethod()->get_option('percentcod');
            if ($selectedPaymentMethodId === 'cod') {
                if ($percentCod !== 0) {
                    $paymentValue = (int) (((int) $percentCod / 100) * $cost);
                } else {
                    $paymentValue = $cost;
                }
            } else {
                $paymentValue = 0;
            }

            $itemsData[] = [
                'ware_key'     => $product->get_id(),
                'payment'      => ['value' => $paymentValue],
                'name'         => $product->get_name(),
                'cost'         => $cost,
                'amount'       => $item->get_quantity(),
                'weight'       => $weight,
                'weight_gross' => $weight + 1,
            ];
        }

        $param['packages'] = [
            'number'  => $orderId,
            'length'  => $length,
            'width'   => $width,
            'height'  => $height,
            'weight'  => $totalWeight,
            'comment' => 'приложена опись',
        ];

        if ($tariffType === Tariff::SHOP_TYPE) {
            $param['packages']['items'] = $itemsData;
        }
    }

    return $param;
}

function convert_currency_cost_to_rub($cost, $currency) {
    global $woocommerce_wpml;

    $multiCurrency = $woocommerce_wpml->get_multi_currency();
    $rates         = $multiCurrency->get_exchange_rates();

    if (!array_key_exists('RUB', $rates)) {
        return $cost;
    }

    $defaultCurrency = '';
    foreach ($rates as $key => $rate) {
        if ($rate === 1) {
            $defaultCurrency = $key;
            break;
        }
    }

    if ($currency === $defaultCurrency) {
        $cost = round($cost * (float) $rates['RUB'], 2);
    } else {
        $costConvertToDefault = round($cost / (float) $rates[$currency], 2);
        $cost                 = round($costConvertToDefault * (float) $rates['RUB'], 2);
    }

    return $cost;
}

function get_packages($orderId, $packageData, $currency) {
    $result = [];
    foreach ($packageData as $key => $package) {
        $data     = get_package_items($package->items, $orderId, $currency);
        $result[] = [
            'number' => $orderId.'_'.StringHelper::generateRandom(5),
            'length' => $package->length,
            'width'  => $package->width,
            'height' => $package->height,
            'weight' => $data['weight'],
            'items'  => $data['items'],
        ];
    }

    return $result;
}

function get_package_items($items, $orderId, $currency) {
    $itemsData   = [];
    $totalWeight = 0;
    foreach ($items as $item) {
        $product     = wc_get_product($item[0]);
        $weight      = $product->get_weight();
        $weightClass = new WeightCalc();
        $weight      = $weightClass->getWeight($weight);
        $totalWeight += (int) $item[2] * $weight;

        $order                   = wc_get_order($orderId);
        $selectedPaymentMethodId = $order->get_payment_method();
        if ($selectedPaymentMethodId === 'cod') {
            $paymentValue = $product->get_price();
        } else {
            $paymentValue = 0;
        }

        $cost = $product->get_price();

        if ($currency !== 'RUB' && function_exists('wcml_get_woocommerce_currency_option')) {
            $cost = convert_currency_cost_to_rub($cost, $currency);
        }

        $itemsData[] = [
            'ware_key'     => $product->get_id(),
            'payment'      => ["value" => $paymentValue],
            'name'         => $product->get_name(),
            'cost'         => $cost,
            'amount'       => $item[2],
            'weight'       => $weight,
            'weight_gross' => $weight + 1,
        ];
    }

    return ['items' => $itemsData, 'weight' => $totalWeight];
}

function cdek_map_display($shippingMethodCurrent) {
    if (is_checkout() && isTariffDestinationCdekOffice($shippingMethodCurrent)) {
        $api = new CdekApi;

        $city = $api->getCityCodeByCityName(CheckoutHelper::getValueFromCurrentSession('city'),
            CheckoutHelper::getValueFromCurrentSession('state'));

        $points = $api->getOffices([
            'city_code' => $city,
        ]);

        include 'templates/public/open-map.php';
    }
}

function isTariffDestinationCdekOffice($shippingMethodCurrent): bool {
    if ($shippingMethodCurrent->get_method_id() !== Config::DELIVERY_NAME) {
        return false;
    }

    $shippingMethodIdSelected = wc_get_chosen_shipping_method_ids()[0];

    if ($shippingMethodCurrent->get_id() !== $shippingMethodIdSelected) {
        return false;
    }

    $tariffCode = explode('_', $shippingMethodIdSelected)[2];

    return Tariff::isTariffToOffice($tariffCode);
}

function cdek_woocommerce_new_order_action($order_id, $order) {
    if (CheckoutHelper::isCdekShippingMethod($order)) {
        $pvzInfo  = CheckoutHelper::getValueFromCurrentSession('pvz_info');
        $pvzCode  = CheckoutHelper::getValueFromCurrentSession('pvz_code');
        $tariffId = getTariffCodeCdekShippingMethodByOrder($order);
        $cityCode = CheckoutHelper::getValueFromCurrentSession('city_code');

        $currency = function_exists('wcml_get_woocommerce_currency_option') ? get_woocommerce_currency() : 'RUB';

        $api = new CdekApi;
        if (empty($cityCode)) {
            $pvzInfo  = $order->get_billing_address_1();
            $cityCode = $api->getCityCodeByCityName($order->get_billing_city(), $order->get_billing_city());
        }
        if (empty($pvzInfo) && Tariff::isTariffToOffice($tariffId)) {
            $pvzInfo = $order->get_billing_address_1();
        }
        $cityData = $api->getCityByCode($cityCode);
        $order->set_shipping_address_1($pvzInfo);
        $order->set_shipping_city($cityData['city']);
        $order->set_shipping_state($cityData['region']);
        $order->save();

        if (Tariff::isTariffToOffice($tariffId)) {
            $shippingMethodArray = $order->get_items('shipping');
            $shippingMethod      = array_shift($shippingMethodArray);
            $shippingMethod->add_meta_data('pvz', $pvzCode.' ('.$pvzInfo.')');
            $shippingMethod->save_meta_data();
        }

        $data = [
            'pvz_code'     => $pvzCode,
            'city_code'    => $cityCode,
            'currency'     => $currency,
            'order_number' => '',
            'order_uuid'   => '',
        ];

        OrderMetaData::addMetaByOrderId($order_id, $data);
    }
}

function getTariffCodeCdekShippingMethodByOrder($order) {
    $shippingMethodArray = $order->get_items('shipping');

    return array_shift($shippingMethodArray)->get_meta('tariff_code');
}

function cdek_checkout_fields($fields) {

    $checkout = WC()->checkout();

    $originalFields = $checkout->get_checkout_fields('billing');

    //Восстанавливаем требуемые поля для чекаута
    foreach (
        [
            'billing_first_name',
            'billing_last_name',
            'billing_city',
            'billing_state',
            'billing_phone',
            'billing_address_1',
        ] as $requiredField
    ) {
        $fields['billing'][$requiredField] = $fields['billing'][$requiredField] ?? $originalFields[$requiredField];
    }

    foreach (['billing_address_1', 'billing_address_2'] as $field) {
        if (isset($fields['billing'][$field])) {
            $fields['billing'][$field]['required'] = false;
        }
    }

    if (Helper::getActualShippingMethod()->get_option('international_mode') === 'yes') {
        $fields['billing']['passport_series']        = [
            'label'             => __('Серия паспорта', 'woocommerce'),
            'required'          => true,
            'class'             => ['form-row-wide'],
            'clear'             => true,
            'custom_attributes' => [
                'maxlength' => 4,
            ],
        ];
        $fields['billing']['passport_number']        = [
            'label'             => __('Номер паспорта', 'woocommerce'),
            'required'          => true,
            'class'             => ['form-row-wide'],
            'clear'             => true,
            'custom_attributes' => [
                'maxlength' => 6,
            ],
        ];
        $fields['billing']['passport_date_of_issue'] = [
            'type'     => 'date',
            'label'    => __('Дата выдачи паспорта', 'woocommerce'),
            'required' => true,
            'class'    => ['form-row-wide'],
            'clear'    => true,
        ];
        $fields['billing']['passport_organization']  = [
            'label'    => __('Орган выдачи паспорта', 'woocommerce'),
            'required' => true,
            'class'    => ['form-row-wide'],
            'clear'    => true,
        ];
        $fields['billing']['tin']                    = [
            'label'             => __('ИНН', 'woocommerce'),
            'required'          => true,
            'class'             => ['form-row-wide'],
            'clear'             => true,
            'custom_attributes' => [
                'maxlength' => 12,
            ],
        ];
        $fields['billing']['passport_date_of_birth'] = [
            'type'     => 'date',
            'label'    => __('Дата рождения', 'woocommerce'),
            'required' => true,
            'class'    => ['form-row-wide'],
            'clear'    => true,
        ];
    }

    return $fields;
}

function cdek_save_custom_checkout_field_to_order($order, $data) {
    foreach (
        [
            'passport_series',
            'passport_number',
            'passport_date_of_issue',
            'passport_organization',
            'tin',
            'passport_date_of_birth',
        ] as $key
    ) {
        if (!isset($_POST[$key])) {
            continue;
        }

        $order->update_meta_data("_$key", sanitize_text_field($_POST[$key]));
    }
}

add_action('admin_notices', 'cdek_display_admin_notices');
function cdek_display_admin_notices() {
    $measurement = get_option('woocommerce_weight_unit');
    if (!in_array($measurement, ['g', 'kg', 'lbs', 'oz'])) {
        echo "<div class='notice notice-info is-dismissible'><p>
            Выбранная единица измерения веса ($measurement) не поддерживается данным плагином.
            Вы можете использовать значение для габаритов товара по умолчанию.
            Также вы можете обратиться в поддержку плагина для дополнительной информации.
            В противном случае, единица измерения будет автоматически обрабатываться как граммы.
            </p></div>";
    }
}
