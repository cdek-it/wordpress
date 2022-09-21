<?php
/**
 * Plugin Name:       CDEKDelivery
 * Description:       Интеграция доставки CDEK
 * Version:           1.0
 * Requires at least: 6.0
 * Requires PHP:      7.2
 * Author:            Klementev Ilya
 */

use Cdek\CdekApi;
use Cdek\Model\SettingData;
use Cdek\Model\Tariff;

if (!function_exists('add_action')) {
    exit();
}

require 'vendor/autoload.php';
function cdek_widget_enqueue_script()
{

    wp_enqueue_style('cdek-css-leaflet', plugin_dir_url(__FILE__) . 'assets/css/leaflet.css');
    wp_enqueue_script('cdek-css-leaflet-min', plugin_dir_url(__FILE__) . 'assets/js/lib/leaflet-src.min.js');
    wp_enqueue_style('cdek-css', plugin_dir_url(__FILE__) . 'assets/css/cdek-map.css');
    wp_enqueue_style('cdek-admin-leaflet-cluster-default', plugin_dir_url(__FILE__) . 'assets/css/MarkerCluster.Default.min.css');
    wp_enqueue_style('cdek-admin-leaflet-cluster', plugin_dir_url(__FILE__) . 'assets/css/MarkerCluster.min.css');
    wp_enqueue_script('cdek-admin-leaflet-cluster', plugin_dir_url(__FILE__) . 'assets/js/lib/leaflet.markercluster-src.min.js');
    addYandexMap();
}

function cdek_admin_enqueue_script()
{
    wp_enqueue_script('cdek-admin-delivery', plugin_dir_url(__FILE__) . 'assets/js/delivery.js', array('jquery'), '1.7.0', true);
    wp_enqueue_script('cdek-admin-leaflet', plugin_dir_url(__FILE__) . 'assets/js/lib/leaflet-src.min.js');
    wp_enqueue_script('cdek-admin-leaflet-cluster', plugin_dir_url(__FILE__) . 'assets/js/lib/leaflet.markercluster-src.min.js');
    wp_enqueue_style('cdek-admin-leaflet', plugin_dir_url(__FILE__) . 'assets/css/leaflet.css');
    wp_enqueue_style('cdek-admin-leaflet-cluster-default', plugin_dir_url(__FILE__) . 'assets/css/MarkerCluster.Default.min.css');
    wp_enqueue_style('cdek-admin-leaflet-cluster', plugin_dir_url(__FILE__) . 'assets/css/MarkerCluster.min.css');
    wp_enqueue_style('cdek-admin-delivery', plugin_dir_url(__FILE__) . 'assets/css/delivery.css');
    addYandexMap();
}

function addYandexMap()
{
    $cdekShipping = WC()->shipping->load_shipping_methods()['official_cdek'];
    $cdekShippingSettings = $cdekShipping->settings;
    if ($cdekShippingSettings['apikey'] !== '') {
        wp_enqueue_script('cdek-admin-yandex-api', 'https://api-maps.yandex.ru/2.1/?lang=en_RU&amp;apikey=' . $cdekShippingSettings['apikey']);
        wp_enqueue_script('cdek-admin-leaflet-yandex', plugin_dir_url(__FILE__) . 'assets/js/lib/Yandex.js');
    } else {
        $cdekShippingSettings['tiles'] = '0';
    }
}

function cdek_register_route()
{
    register_rest_route('cdek/v1', '/get-region', array(
        'methods' => 'GET',
        'callback' => 'get_region',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('cdek/v1', '/get-city-code', array(
        'methods' => 'GET',
        'callback' => 'get_city_code',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('cdek/v1', '/get-pvz', array(
        'methods' => 'GET',
        'callback' => 'get_pvz',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('cdek/v1', '/create-order', array(
        'methods' => 'POST',
        'callback' => 'create_order',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('cdek/v1', '/create-order', array(
        'methods' => 'GET',
        'callback' => 'create_order',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('cdek/v1', '/delete-order', array(
        'methods' => 'GET',
        'callback' => 'delete_order',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('cdek/v1', '/get-waybill', array(
        'methods' => 'GET',
        'callback' => 'get_waybill',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('cdek/v1', '/check-auth', array(
        'methods' => 'GET',
        'callback' => 'check_auth',
        'permission_callback' => '__return_true'
    ));
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function get_packages($orderId, $packageData) {
    $result = [];
    foreach ($packageData as $package) {
        $data = get_package_items($package->items);

        $weight = 0;
        if ($data['weight'] === 0) {
            $cdekShipping = WC()->shipping->load_shipping_methods()['official_cdek'];
            $cdekShippingSettings = $cdekShipping->settings;
            $weight = (int)$cdekShippingSettings['default_weight'];
            if ($weight === 0) {
                $weight = 1;
            }
        } else {
            $weight = $data['weight'];
        }

        $result[] = [
            'number' => $orderId . '_' . generateRandomString(5),
            'length' => $package->length,
            'width' => $package->width,
            'height' => $package->height,
            'weight' => $weight,
            'items' => $data['items']
        ];
    }

    return $result;
}

function get_package_items($items) {
    $itemsData = [];
    $weightTotal = 0;
    foreach ($items as $item) {
        $product = wc_get_product($item[0]);
        $weight = 1;
        if ((int)$product->get_weight() === 0) {
            $cdekShipping = WC()->shipping->load_shipping_methods()['official_cdek'];
            $cdekShippingSettings = $cdekShipping->settings;
            $weight = (int)$cdekShippingSettings['default_weight'];
            if ($weight === 0) {
                $weight = 1;
            }
        } else {
            $weight = (int)$product->get_weight();
        }
        $weightTotal += ($weight * 1000) * (int)$item[2];
        $itemsData[] = [
            "ware_key" => $product->get_id(),
            "payment" => ["value" => 0],
            "name" => $product->get_name(),
            "cost" => $product->get_price(),
            "amount" => $item[2],
            "weight" => $weight * 1000,
            "weight_gross" => ($weight * 1000) + 1,
        ];
    }
    return ['items' => $itemsData, 'weight' => $weightTotal];
}

function create_order($data)
{
    $param = [];
    $orderId = $data->get_param('package_order_id');
    $param = setPackage($data, $orderId, $param);
    $order = wc_get_order( $orderId );
    $pvzCode = $order->get_meta('pvz_code');
    $tariffId = $order->get_meta('tariff_id');
    $cityCode = $order->get_meta('city_code');
    $cityName = $order->get_shipping_city();
    $cityAddress = $order->get_shipping_address_1();

    if (empty($cityCode)) {
        $cityName = $order->get_shipping_city();
        $stateName = $order->get_shipping_state();
        $cityCode = CdekApi()->getCityCodeByCityName($cityName, $stateName);
    }

    if ((int) Tariff::getTariffTypeToByCode($tariffId)) {
        $param['delivery_point'] = $pvzCode;
    } else {
        $param['to_location'] = [
            'code' => $cityCode,
            'city' => $cityName,
            'address' => $cityAddress
        ];
    }

    $param['recipient'] = [
        'name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
        'phones' => [
            'number' => $order->get_billing_phone()
        ]
    ];
    $param['tariff_code'] = $tariffId;
    $param['print'] = 'waybill';

    $orderDataJson = CdekApi()->createOrder($param);
    $orderData = json_decode($orderDataJson);

    if ($orderData->requests[0]->state === 'INVALID') {
        return json_encode(['state' => 'error', 'message' => 'Ошибка. Заказ не создан. (' . $orderData->requests[0]->errors[0]->message . ')']);
    }

    $code = $orderData->entity->uuid;
    $orderInfoJson = CdekApi()->getOrder($code);
    $orderInfo = json_decode($orderInfoJson);
    $cdekNumber = null;
    if (property_exists($orderInfo->entity, 'cdek_number')) {
        $cdekNumber = $orderInfo->entity->cdek_number;
    }

    if (empty($cdekNumber)) {
        $cdekNumber = $code;
    }

    $order->update_meta_data('cdek_order_uuid', $cdekNumber);
    $order->update_meta_data('cdek_order_waybill', $orderData->entity->uuid);
    $order->save_meta_data();
    return json_encode(['state' => 'success', 'code' => $cdekNumber, 'waybill' => '/wp-json/cdek/v1/get-waybill?number=' . $orderData->entity->uuid]);
}

function setPackage($data, $orderId, array $param)
{
    $cdekShipping = WC()->shipping->load_shipping_methods()['official_cdek'];
    $cdekShippingSettings = $cdekShipping->settings;
    if ($cdekShippingSettings['has_packages'] === 'yes') {
        $packageData = json_decode($data->get_param('package_data'));
        $param['packages'] = get_packages($orderId, $packageData);
    } else {
        $length = $data->get_param('package_length');
        $width = $data->get_param('package_width');
        $height = $data->get_param('package_height');
        $order = wc_get_order($orderId);
        $items = $order->get_items();
        $itemsData = [];
        $weightTotal = 0;
        foreach ($items as $item) {
            $product = $item->get_product();
            $weight = (int)$product->get_weight();
            if ($weight === 0) {
                $cdekShipping = WC()->shipping->load_shipping_methods()['official_cdek'];
                $cdekShippingSettings = $cdekShipping->settings;
                $weight = (int)$cdekShippingSettings['default_weight'];
                if ($weight === 0) {
                    $weight = 1;
                }
            }

            $weightTotal += $weight;

            $itemsData[] = [
                "ware_key" => $product->get_id(),
                "payment" => ["value" => 0],
                "name" => $product->get_name(),
                "cost" => $product->get_price(),
                "amount" => $item->get_quantity(),
                "weight" => $weight * 1000,
                "weight_gross" => ($weight * 1000) + 1,
            ];
        }
        $weightTotal = $weightTotal * 1000;

        $param['packages'] = [
            'number' => $orderId,
            'length' => $length,
            'width' => $width,
            'height' => $height,
            'weight' => $weightTotal,
            'items' => $itemsData
        ];
    }
    return $param;
}

function get_waybill($data)
{
    $waybillData = CdekApi()->createWaybill($data->get_param('number'));
    $waybill = json_decode($waybillData);

    if ($waybill->requests[0]->state === 'INVALID' || property_exists($waybill->requests[0], 'errors')) {
        echo '
        Не удалось создать квитанцию. 
        Для решения проблемы, попробуй пересоздать заказ. Нажмите кнопку "Отменить"
        и введите габариты упаковки повторно.';
        exit();
    }

    $rawFile = CdekApi()->getWaybill($waybill->entity->uuid);
    header("Content-type:application/pdf");
    echo $rawFile;
    exit();
}

function check_auth($data)
{
    return CdekApi()->checkAuth($data->get_param('client_id'), $data->get_param('client_secret'));
}

function get_region($data)
{
    return CdekApi()->getRegion($data->get_param('city'));
}

function get_city_code($data)
{
    return CdekApi()->getCityCodeByCityName($data->get_param('city_name'), $data->get_param('state_name'));
}

function get_pvz($data)
{
    return cdekApi()->getPvz($data->get_param('city_code'));
}

function delete_order($data)
{
    $order = wc_get_order($data->get_param('order_id'));
    $order->delete_meta_data('cdek_order_uuid', '');
    $order->save_meta_data();
    return cdekApi()->deleteOrder($data->get_param('number'));
}

function cdekApi()
{
    $settingData = getSettingData();
    return new CdekApi($settingData);
}

function getSettingData()
{
    $settingData = new SettingData();
    $cdekShipping = WC()->shipping->load_shipping_methods()['official_cdek'];
    $cdekShippingSettings = $cdekShipping->settings;
    $settingData->setGrantType('client_credentials');
    $settingData->setClientId($cdekShippingSettings['client_id']);
    $settingData->setClientSecret($cdekShippingSettings['client_secret']);
    $settingData->setDeveloperKey('7wV8tk&r6VH4zK:1&0uDpjOkvM~qngLl');
    $settingData->setTariffCode($cdekShippingSettings['rate']);
    $settingData->setSellerName($cdekShippingSettings['seller_name']);
    $settingData->setSellerPhone($cdekShippingSettings['seller_phone']);
    $settingData->setFromCity($cdekShippingSettings['city_code_value']);
    $settingData->setFromAddress($cdekShippingSettings['street']);
    $settingData->setPvzCode($cdekShippingSettings['pvz_code']);
    $settingData->setPvzAddress($cdekShippingSettings['pvz_info']);
    $settingData->setShipperName((string)$cdekShippingSettings['shipper_name']);
    $settingData->setShipperAddress((string)$cdekShippingSettings['shipper_address']);
    $settingData->setSellerAddress((string)$cdekShippingSettings['seller_address']);
    return $settingData;
}

function cdek_shipping_method()
{
    if (!class_exists('Cdek_Shipping_Method')) {
        require_once(plugin_dir_path(__FILE__) . 'src/Cdek_Shipping_Method.php');
    }
}

function cdek_map_display($shippingMethod)
{
    $current = WC()->session->get( 'chosen_shipping_methods')[0];
    if ($shippingMethod->get_method_id() === 'official_cdek' &&
        $shippingMethod->get_meta_data()['type'] === '1' &&
        $shippingMethod->get_id() === $current &&
        $_SERVER['REQUEST_URI'] !== '/cart/' &&
        $_SERVER['REQUEST_URI'] !== '/?wc-ajax=update_shipping_method') {
        $cdekShipping = WC()->shipping->load_shipping_methods()['official_cdek'];
        $cdekShippingSettings = $cdekShipping->settings;
        $layerMap = $cdekShippingSettings['tiles'];
        if ($cdekShippingSettings['apikey'] === "") {
            $layerMap = "0";
        }
        include 'templates/public/open-map.php';
    }
}

function cdek_checkout_shipping()
{
    include 'templates/public/map.php';
}

function is_pvz_code() {
    $pvzCode = $_POST['pvz_code'];
    $tariff = explode('_', $_POST['shipping_method'][0])[1];
    if ((int)Tariff::getTariffTypeToByCode($tariff) && empty($pvzCode)) {
        wc_add_notice( __( 'Не выбран пункт выдачи заказа.' ), 'error' );
    }
}

function cdek_woocommerce_new_order_action($order_id, $order)
{
    $pvzInfo = $_POST['pvz_info'];
    $pvzCode = $_POST['pvz_code'];
    $tariffId = explode('_', $_POST['shipping_method'][0])[2];
    $cityCode = $_POST['city_code'];

    $order->set_meta_data(['pvz_info' => $pvzInfo, 'pvz_code' => $pvzCode, 'tariff_id' => $tariffId, 'city_code' => $cityCode]);
    $order->update_meta_data('pvz_info', $pvzInfo);
    $order->update_meta_data('pvz_code', $pvzCode);
    $order->update_meta_data('tariff_id', $tariffId);
    $order->update_meta_data('city_code', $cityCode);
    $order->update_meta_data('cdek_shipping', true);
    $order->save_meta_data();
}

function add_cdek_shipping_method($methods)
{
    $methods['official_cdek'] = 'CdekShippingMethod';
    return $methods;
}

function cdek_admin_order_data_after_shipping_address ($order)
{
    $checkCdek = $order->get_meta('cdek_shipping');
    $orderUuid = $order->get_meta('cdek_order_uuid');
    $tariffId = $order->get_meta('tariff_id');
    if ((int)$checkCdek || !empty($orderUuid) || !empty($tariffId)) {
        $waybill = $order->get_meta('cdek_order_waybill');
        $orderId = $order->get_id();
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[$item['product_id']] = ['name' => $item['name'], 'quantity' => $item['quantity']];
        }

        $cdekShipping = WC()->shipping->load_shipping_methods()['official_cdek'];
        $cdekShippingSettings = $cdekShipping->settings;
        $hasPackages = false;
        if ($cdekShippingSettings['has_packages'] === 'yes') {
            $hasPackages = true;
        }

        include 'templates/admin/create-order.php';
    }
}

add_filter('woocommerce_admin_order_data_after_shipping_address', 'cdek_admin_order_data_after_shipping_address');
add_filter( 'woocommerce_new_order' , 'cdek_woocommerce_new_order_action', 10, 2);
add_filter( 'woocommerce_shipping_methods', 'add_cdek_shipping_method' );
add_action('woocommerce_shipping_init', 'cdek_shipping_method');
add_action('woocommerce_after_shipping_rate', 'cdek_map_display', 10, 2);
add_action('woocommerce_checkout_shipping', 'cdek_checkout_shipping');
add_action('woocommerce_checkout_process', 'is_pvz_code');
add_action('wp_enqueue_scripts', 'cdek_widget_enqueue_script');
add_action('admin_enqueue_scripts', 'cdek_admin_enqueue_script');
add_action('rest_api_init', 'cdek_register_route');