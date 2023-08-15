<?php
/**
 * Plugin Name:       CDEKDelivery
 * Plugin URI:        https://www.cdek.ru/ru/integration/modules/33
 * Description:       Интеграция доставки CDEK
 * Version:           dev
 * Requires at least: 6.0
 * Requires PHP:      7.2
 * Author:            CDEK IT
 * WC requires at least: 6.0
 * WC tested up to: 7.0
 */

use Automattic\WooCommerce\Utilities\OrderUtil;
use Cdek\CallCourier;
use Cdek\CdekApi;
use Cdek\Config;
use Cdek\CreateOrder;
use Cdek\Helper;
use Cdek\Helpers\CheckoutHelper;
use Cdek\Loader;
use Cdek\Model\CourierMetaData;
use Cdek\Model\OrderMetaData;
use Cdek\Model\Tariff;
use Cdek\WeightCalc;

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
add_action('woocommerce_checkout_process', 'is_pvz_code');
add_action('wp_footer', 'cdek_add_script_update_shipping_method');
add_filter('woocommerce_checkout_fields', 'cdek_add_custom_checkout_field', 1090);
add_action('woocommerce_checkout_create_order', 'cdek_save_custom_checkout_field_to_order', 10, 2);
function remove_address_field_requirement($fields) {
    $fields['billing']['billing_address_1']['required'] = false;
    $fields['billing']['billing_address_2']['required'] = false;

    return $fields;
}


add_filter('woocommerce_checkout_fields', 'remove_address_field_requirement');

function getCityCode($city_code, $order) {
    $api      = new CdekApi();
    $cityCode = $city_code;
    if (empty($cityCode)) {
        $cityCode = $api->getCityCodeByCityName($order->get_shipping_city(), $order->get_shipping_state());
    }

    return $cityCode;
}

function setPackage($data, $orderId, $currency) {
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
            'number' => $orderId,
            'length' => $length,
            'width'  => $width,
            'height' => $height,
            'weight' => $totalWeight,
            'items'  => $itemsData,
        ];
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
            'number' => $orderId.'_'.Helper::generateRandomString(5),
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
            "ware_key"     => $product->get_id(),
            "payment"      => ["value" => $paymentValue],
            "name"         => $product->get_name(),
            "cost"         => $cost,
            "amount"       => $item[2],
            "weight"       => $weight,
            "weight_gross" => $weight + 1,
        ];
    }

    return ['items' => $itemsData, 'weight' => $totalWeight];
}

function cdek_map_display($shippingMethodCurrent) {
    if (is_checkout() && isTariffTypeFromStore($shippingMethodCurrent)) {
        $cdekShippingMethod = Helper::getActualShippingMethod();
        $layerMap           = $cdekShippingMethod->get_option('map_layer');
        if ($cdekShippingMethod->get_option('yandex_map_api_key') === "") {
            $layerMap = "0";
        }

        $meta   = $shippingMethodCurrent->get_meta_data();
        $weight = $meta['total_weight_kg'];

        $api = new CdekApi;

        $city = $api->getCityCodeByCityName(CheckoutHelper::getValueFromCurrentSession('city'),
            CheckoutHelper::getValueFromCurrentSession('state'));

        $points = $api->getPvz($city, $weight);

        include 'templates/public/open-map.php';
    }
}

function isTariffTypeFromStore($shippingMethodCurrent) {
    if ($shippingMethodCurrent->get_method_id() !== 'official_cdek') {
        return false;
    }

    $shippingMethodIdSelected = WC()->session->get('chosen_shipping_methods')[0];

    if ($shippingMethodCurrent->get_id() !== $shippingMethodIdSelected) {
        return false;
    }

    $tariffCode = explode('_', $shippingMethodIdSelected)[2];

    return (bool) (int) Tariff::getTariffTypeToByCode($tariffCode);
}

function cdek_add_update_form_billing($fragments) {

    $checkout = WC()->checkout();

    parse_str($_POST['post_data'], $fields_values);

    ob_start();

    echo '<div class="woocommerce-billing-fields__field-wrapper">';

    $fields = $checkout->get_checkout_fields('billing');

    foreach ($fields as $key => $field) {
        $value = $checkout->get_value($key);

        if (!$value && !empty($fields_values[$key])) {
            $value = $fields_values[$key];
        }

        woocommerce_form_field($key, $field, $value);
    }

    echo '</div>';

    $fragments['.woocommerce-billing-fields__field-wrapper'] = ob_get_clean();

    return $fragments;
}

function getShipToDestination() {
    $shipToDestination = get_option('woocommerce_ship_to_destination');
    if ($shipToDestination === 'billing_only') {
        return 'billing';
    }

    return $shipToDestination;
}

function cdek_add_script_update_shipping_method() {
    if (is_checkout()) {
        ?>
        <script>
            jQuery(document).on('change', '.shipping_method', function() {
                jQuery(document.body).trigger('update_checkout');
            });
        </script>
        <?php
    }
}

function cdek_woocommerce_new_order_action($order_id, $order) {
    if (isCdekShippingMethod($order)) {
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
        if (empty($pvzInfo) && Tariff::isTariffToStoreByCode($tariffId)) {
            $pvzInfo = $order->get_billing_address_1();
        }
        $cityData = $api->getCityByCode($cityCode);
        $order->set_shipping_address_1($pvzInfo);
        $order->set_shipping_city($cityData['city']);
        $order->set_shipping_state($cityData['region']);
        $order->save();

        if (Tariff::isTariffToStoreByCode($tariffId)) {
            $shippingMethodArray = $order->get_items('shipping');
            $shippingMethod      = array_shift($shippingMethodArray);
            $shippingMethod->add_meta_data('pvz', $pvzCode.' ('.$pvzInfo.')');
            $shippingMethod->save_meta_data();
        }

        $data = [
            'pvz_address'  => $pvzInfo,
            'pvz_code'     => $pvzCode,
            'tariff_id'    => $tariffId,
            'city_code'    => $cityCode,
            'currency'     => $currency,
            'order_number' => '',
            'order_uuid'   => '',
        ];

        OrderMetaData::addMetaByOrderId($order_id, $data);
    }
}

function add_custom_order_meta_box() {
    global $post;
    if ($post && OrderUtil::is_order($post->ID, wc_get_order_types())) {
        $order_id = $post->ID;
        $order    = wc_get_order($order_id);
        if (isCdekShippingMethod($order)) {
            $api = new CdekApi();
            if ($api->checkAuth()) {
                $createOrder = new CreateOrder();
                $createOrder->deleteIfNotExist($order_id);

                $callCourier = new CallCourier();
                $callCourier->deleteIfNotExist($order_id);
                CourierMetaData::getMetaByOrderId($order_id);
                //Сбор данных
                $orderWP       = $order->get_id();
                $postOrderData = OrderMetaData::getMetaByOrderId($orderWP);

                $orderNumber   = getOrderNumber($postOrderData);
                $orderUuid     = getOrderUuid($postOrderData);
                $items         = getItems($order);
                $dateMin       = date('Y-m-d');
                $dateMax       = getDateMax($dateMin);
                $courierNumber = getCourierNumber($order_id);

                add_meta_box('cdek_create_order_box', 'CDEKDelivery', 'render_cdek_create_order_box', 'shop_order',
                    'side', 'core', [
                        'status'        => true,
                        'hasPackages'   => isHasPackages(),
                        'orderNumber'   => $orderNumber,
                        'orderIdWP'     => $orderWP,
                        'orderUuid'     => $orderUuid,
                        'dateMin'       => $dateMin,
                        'dateMax'       => $dateMax,
                        'items'         => $items,
                        'courierNumber' => $courierNumber,
                        'fromDoor'      => Tariff::isTariffFromDoorByCode($postOrderData['tariff_id']),
                    ],);
            } else {
                add_meta_box('cdek_create_order_box', 'CDEKDelivery', 'render_cdek_create_order_box', 'shop_order',
                    'side', 'core', [
                        'status' => false,
                    ],);

            }
        }
    }


}

/**
 * @param $order
 *
 * @return array
 */
function getItems($order): array {
    $items = [];
    foreach ($order->get_items() as $item) {
        $items[$item['product_id']] = ['name' => $item['name'], 'quantity' => $item['quantity']];
    }

    return $items;
}

/**
 * @param $postOrderData
 *
 * @return mixed
 */
function getOrderUuid($postOrderData) {
    if (array_key_exists('cdek_order_waybill', $postOrderData)) {
        $orderUuid = $postOrderData['cdek_order_waybill'];
    } else {
        $orderUuid = $postOrderData['order_uuid'];
    }

    return $orderUuid;
}

/**
 * @param $postOrderData
 *
 * @return mixed
 */
function getOrderNumber($postOrderData) {
    if (array_key_exists('cdek_order_uuid', $postOrderData)) {
        $orderNumber = $postOrderData['cdek_order_uuid'];
    } else {
        $orderNumber = $postOrderData['order_number'];
    }

    return $orderNumber;
}

/**
 * @param int $order_id
 *
 * @return mixed|string
 */
function getCourierNumber(int $order_id) {
    $courierMeta   = CourierMetaData::getMetaByOrderId($order_id);
    $courierNumber = '';
    if (!empty($courierMeta)) {
        $courierNumber = $courierMeta['courier_number'];
    }

    return $courierNumber;
}

/**
 * @param $dateMin
 *
 * @return false|string
 */
function getDateMax($dateMin) {
    $dateMaxUnix = strtotime($dateMin." +31 days");

    return date('Y-m-d', $dateMaxUnix);
}

/**
 * @return bool
 */
function isHasPackages(): bool {
    return Helper::getActualShippingMethod()->get_option('has_packages_mode') === 'yes';
}

add_action('add_meta_boxes', 'add_custom_order_meta_box');

function render_cdek_create_order_box($post, $metabox) {
    $args = $metabox['args'];
    if ($args['status']) {
        $hasPackages   = $args['hasPackages'];
        $orderNumber   = $args['orderNumber'];
        $orderIdWP     = $args['orderIdWP'];
        $orderUuid     = $args['orderUuid'];
        $dateMin       = $args['dateMin'];
        $dateMax       = $args['dateMax'];
        $items         = $args['items'];
        $courierNumber = $args['courierNumber'];
        $fromDoor      = $args['fromDoor'];
        ob_start();
        include 'templates/admin/create-order.php';
        $content = ob_get_clean();
        echo $content;
    } else {
        $settings_page_url = admin_url('admin.php?page=wc-settings&tab=shipping&section=official_cdek');
        echo '<div class="cdek_create_order_box">';
        echo '<h4>Авторизация не пройдена</h4>';
        echo '<p>Введите корректные идентификатор и секретный ключ клиента в <a href="'.$settings_page_url.'">настройках</a> плагина CDEKDelivery</p>';
        echo '</div>';
    }
}

function getTariffCodeCdekShippingMethodByOrder($order) {
    $shippingMethodArray = $order->get_items('shipping');

    return array_shift($shippingMethodArray)->get_meta('tariff_code');
}

function isCdekShippingMethod($order) {
    $shippingMethodArray = $order->get_items('shipping');
    if (empty($shippingMethodArray)) {
        return false;
    }
    $shippingMethod   = array_shift($shippingMethodArray);
    $shippingMethodId = $shippingMethod->get_method_id();

    return $shippingMethodId === 'official_cdek';
}

function cdek_add_custom_checkout_field($fields) {

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
    if (isset($_POST['passport_series'])) {
        $order->update_meta_data('_passport_series', sanitize_text_field($_POST['passport_series']));
    }
    if (isset($_POST['passport_number'])) {
        $order->update_meta_data('_passport_number', sanitize_text_field($_POST['passport_number']));
    }
    if (isset($_POST['passport_date_of_issue'])) {
        $order->update_meta_data('_passport_date_of_issue', sanitize_text_field($_POST['passport_date_of_issue']));
    }
    if (isset($_POST['passport_organization'])) {
        $order->update_meta_data('_passport_organization', sanitize_text_field($_POST['passport_organization']));
    }
    if (isset($_POST['tin'])) {
        $order->update_meta_data('_tin', sanitize_text_field($_POST['tin']));
    }
    if (isset($_POST['passport_date_of_birth'])) {
        $order->update_meta_data('_passport_date_of_birth', sanitize_text_field($_POST['passport_date_of_birth']));
    }
}

function is_pvz_code() {
    $shippingMethodIdSelected = WC()->session->get('chosen_shipping_methods')[0];

    if (strpos($shippingMethodIdSelected, Config::DELIVERY_NAME) !== false) {
        $api      = new CdekApi();
        $cityCode = $api->getCityCodeByCityName(CheckoutHelper::getValueFromCurrentSession('city'),
            CheckoutHelper::getValueFromCurrentSession('state'));
        if ($cityCode === -1) {
            wc_add_notice(__('Не удалось определить населенный пункт.'), 'error');
        }

        $tariffCode = getTariffCodeByShippingMethodId($shippingMethodIdSelected);
        if (checkTariffFromStoreByTariffCode($tariffCode)) {
            if (empty(CheckoutHelper::getValueFromCurrentSession('pvz_code'))) {
                $pvzCodeTmp = WC()->session->get('pvz_code');
                if (empty($pvzCodeTmp[0]['pvz_code'])) {
                    wc_add_notice(__('Не выбран пункт выдачи заказа.'), 'error');
                } else {
                    $_POST['pvz_code']    = $pvzCodeTmp[0]['pvz_code'];
                    $_POST['pvz_address'] = $pvzCodeTmp[0]['pvz_address'];
                    $_POST['city_code']   = $pvzCodeTmp[0]['city_code'];
                    WC()->session->set('pvz_code', null);
                }
            }
        } elseif (empty(CheckoutHelper::getValueFromCurrentSession('address_1'))) {
            wc_add_notice(__('Нет адреса отправки.'), 'error');
        }
    }
}

function getTariffCodeByShippingMethodId($shippingMethodId) {
    return explode('_', $shippingMethodId)[2];
}

function checkTariffFromStoreByTariffCode($tariffCode): bool {
    return (bool) Tariff::getTariffTypeToByCode($tariffCode);
}
