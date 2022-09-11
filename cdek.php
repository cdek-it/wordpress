<?php
/**
 * Plugin Name:       CDEKDelivery
 * Description:       Интеграция доставки CDEK
 * Version:           1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
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

    wp_enqueue_style('testcssleaflet', plugin_dir_url(__FILE__) . 'assets/css/leaflet.css');
    wp_enqueue_script('testleaflet', plugin_dir_url(__FILE__) . 'assets/js/lib/leaflet-src.min.js');
    wp_enqueue_style('testcss', plugin_dir_url(__FILE__) . 'assets/css/test.css');
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

/**
 * @return void
 */
function addYandexMap(): void
{
    $cdekShipping = WC()->shipping->load_shipping_methods()['cdek'];
    $cdekShippingSettings = $cdekShipping->settings;
    wp_enqueue_script('cdek-admin-yandex-api', 'https://api-maps.yandex.ru/2.1/?lang=en_RU&amp;apikey=' . $cdekShippingSettings['apikey']);
    wp_enqueue_script('cdek-admin-leaflet-yandex', plugin_dir_url(__FILE__) . 'assets/js/lib/Yandex.js');
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
        'methods' => 'GET',
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
}

function create_order($data)
{
    $param = [];
    $orderId = $data->get_param('package_order_id');
    $length = $data->get_param('package_length');
    $width = $data->get_param('package_width');
    $height = $data->get_param('package_height');
    $order = wc_get_order( $orderId );
    $pvzInfo = $order->get_meta('pvz_info');
    $pvzCode = $order->get_meta('pvz_code');
    $tariffId = $order->get_meta('tariff_id');
    $cityCode = $order->get_meta('city_code');
    $cityName = $order->get_shipping_city();
    $cityAddress = $order->get_shipping_address_1();

    if (empty($cityCode)) {
        $cityName = $order->get_shipping_city();
        $cityPostcode = $order->get_shipping_postcode();
        $cityCode = CdekApi()->getCityCode($cityName, $cityPostcode);
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

    $items = $order->get_items();
    $itemsData = [];
    $weightTotal = 0;
    foreach ($items as $item) {
        $product = $item->get_product();
        $weightTotal += (int) $product->get_weight();
        $itemsData[] = [
            "ware_key" => $product->get_id(),
            "payment" => ["value" => 0],
            "name" => $product->get_name(),
            "cost" => $product->get_price(),
            "amount" => $item->get_quantity(),
            "weight" => (int)$product->get_weight() * 1000,
            "weight_gross" => ((int)$product->get_weight() * 1000) + 1,
        ];
    }
    $weightTotal = $weightTotal * 1000;

    $param['recipient'] = [
        'name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
        'phones' => [
            'number' => $order->get_billing_phone()
        ]
    ];
    $param['tariff_code'] = $tariffId;
    $param['packages'] = [
        'number' => $orderId,
        'length' => $length,
        'width' => $width,
        'height' => $height,
        'weight' => $weightTotal,
        'items' => $itemsData
    ];
    $param['print'] = 'waybill';

    $orderDataJson = CdekApi()->createOrder($param);
    $orderData = json_decode($orderDataJson);

    if ($orderData->requests[0]->state === 'INVALID') {
        return json_encode(['state' => 'error', 'message' => 'Ошибка. Заказ не создан. (' . $orderData->requests[0]->errors[0]->message . ')']);
    }

    $code = $orderData->entity->uuid;
    $orderInfoJson = CdekApi()->getOrder($code);
    $orderInfo = json_decode($orderInfoJson);
    $waybill = $orderData->related_entities[0]->uuid;
    $cdekNumber = null;
    if (property_exists($orderInfo->entity, 'cdek_number')) {
        $cdekNumber = $orderInfo->entity->cdek_number;
    }

    if (empty($cdekNumber)) {
        $cdekNumber = $code;
    }

    $order->update_meta_data('cdek_order_uuid', $cdekNumber);
    $order->update_meta_data('cdek_order_waybill', $waybill);
    $order->save_meta_data();
    return json_encode(['state' => 'success', 'code' => $cdekNumber, 'waybill' => '/wp-json/cdek/v1/get-waybill?number=' . $waybill]);
}

function get_waybill($data)
{
    $rawFile = CdekApi()->getWaybill($data->get_param('number'));
    header("Content-type:application/pdf");
    echo $rawFile;
    exit();
}

function get_region($data)
{
    return CdekApi()->getRegion($data->get_param('city'));
}

function get_city_code($data)
{
    return CdekApi()->getCityCode($data->get_param('city_name'), $data->get_param('zip_code'));
}

function get_pvz($data)
{
    return cdekApi()->getPvz($data->get_param('city_code'));
}

function delete_order($data) {
    $order = wc_get_order($data->get_param('order_id'));
    $order->delete_meta_data('cdek_order_uuid', '');
    $order->save_meta_data();
    return cdekApi()->deleteOrder($data->get_param('number'));
}

function cdekApi(): CdekApi
{
    $settingData = getSettingData();
    return new CdekApi($settingData);
}

function getSettingData(): SettingData
{
    $settingData = new SettingData();
    $cdekShipping = WC()->shipping->load_shipping_methods()['cdek'];
    $cdekShippingSettings = $cdekShipping->settings;
    $settingData->setMode($cdekShippingSettings['mode']);
    $settingData->setGrantType($cdekShippingSettings['grant_type']);
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

add_action('wp_enqueue_scripts', 'cdek_widget_enqueue_script');
add_action('admin_enqueue_scripts', 'cdek_admin_enqueue_script');
add_action('rest_api_init', 'cdek_register_route');


function cdek_shipping_method()
{
    if (!class_exists('Cdek_Shipping_Method')) {
        require_once(plugin_dir_path(__FILE__) . 'src/Cdek_Shipping_Method.php');
    }
}

function cdek_map_display($shippingMethod)
{
    $current = WC()->session->get( 'chosen_shipping_methods')[0];
    if ($shippingMethod->get_method_id() === 'cdek' &&
        $shippingMethod->get_meta_data()['type'] === '1' &&
        $shippingMethod->get_id() === $current &&
        $_SERVER['REQUEST_URI'] !== '/cart/') {
        $cdekShipping = WC()->shipping->load_shipping_methods()['cdek'];
        $cdekShippingSettings = $cdekShipping->settings;
        $layerMap = $cdekShippingSettings['tiles'];
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
    $tariffId = explode('_', $_POST['shipping_method'][0])[1];
    $cityCode = $_POST['city_code'];

    $order->set_meta_data(['pvz_info' => $pvzInfo, 'pvz_code' => $pvzCode, 'tariff_id' => $tariffId, 'city_code' => $cityCode]);
    $order->update_meta_data('pvz_info', $pvzInfo);
    $order->update_meta_data('pvz_code', $pvzCode);
    $order->update_meta_data('tariff_id', $tariffId);
    $order->update_meta_data('city_code', $cityCode);
    $order->save_meta_data();
}

function add_cdek_shipping_method($methods)
{
    $methods['cdek'] = 'CdekShippingMethod';
    return $methods;
}

function cdek_admin_order_data_after_shipping_address ($order)
{
    $orderUuid = $order->get_meta('cdek_order_uuid');
    $waybill = $order->get_meta('cdek_order_waybill');
    include 'templates/admin/create-order.php';
}

add_filter('woocommerce_admin_order_data_after_shipping_address', 'cdek_admin_order_data_after_shipping_address');
add_action('woocommerce_shipping_init', 'cdek_shipping_method');
add_action('woocommerce_after_shipping_rate', 'cdek_map_display', 10, 2);
add_action('woocommerce_checkout_shipping', 'cdek_checkout_shipping');
add_filter( 'woocommerce_new_order' , 'cdek_woocommerce_new_order_action', 10, 2);
add_filter( 'woocommerce_shipping_methods', 'add_cdek_shipping_method' );
add_action('woocommerce_checkout_process', 'is_pvz_code');