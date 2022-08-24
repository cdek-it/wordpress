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

    $orderDataJson = CdekApi()->createOrder($param);
    $orderData = json_decode($orderDataJson);

    $code = $orderData->entity->uuid;
    $order->update_meta_data('cdek_order_uuid', $code);
    $order->save_meta_data();

    return $code;
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
    return $settingData;
}

add_action('wp_enqueue_scripts', 'cdek_widget_enqueue_script');
add_action('admin_enqueue_scripts', 'cdek_admin_enqueue_script');
add_action('rest_api_init', 'cdek_register_route');


/*
 * Check if WooCommerce is active
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    function cdek_shipping_method()
    {
        if (!class_exists('Cdek_Shipping_Method')) {
            class Cdek_Shipping_Method extends WC_Shipping_Method
            {
                public function __construct()
                {
                    $this->id = 'cdek';
                    $this->method_title = __('Cdek Shipping', 'cdek');
                    $this->method_description = __('Custom Shipping Method for Cdek', 'cdek');

                    $this->init();

                    $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
                    $this->title = isset($this->settings['title']) ? $this->settings['title'] : __('Cdek Shipping', 'cdek');
                }

                function init()
                {
                    $this->init_form_fields();
                    $this->init_settings();
                    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
                }

                function init_form_fields()
                {

                    $this->form_fields = array(

                        'enabled' => array(
                            'title' => __('Enable', 'cdek'),
                            'type' => 'checkbox',
                            'description' => __('Enable this shipping.', 'cdek'),
                            'default' => 'yes'
                        ),

                        'grant_type' => array(
                            'title' => __('Тип аутентификации', 'cdek'),
                            'type' => 'text',
                            'description' => __('Например "client_credentials"', 'cdek'),
                            'default' => __('client_credentials', 'cdek')
                        ),

                        'client_id' => array(
                            'title' => __('Идентификатор клиента', 'cdek'),
                            'type' => 'text',
                            'description' => __('Для тестового режима "EMscd6r9JnFiQ3bLoyjJY6eM78JrJceI"', 'cdek'),
                            'default' => __('EMscd6r9JnFiQ3bLoyjJY6eM78JrJceI', 'cdek')
                        ),

                        'client_secret' => array(
                            'title' => __('Секретный ключ клиента', 'cdek'),
                            'type' => 'text',
                            'description' => __('Для тестового режима "PjLZkKBHEiLK3YsjtNrt3TGNG0ahs3kG"', 'cdek'),
                            'default' => __('PjLZkKBHEiLK3YsjtNrt3TGNG0ahs3kG', 'cdek')
                        ),

                        'seller_name' => array(
                            'title' => __('ФИО', 'cdek'),
                            'type' => 'text',
                            'default' => __('Клементьев Илья', 'cdek')
                        ),

                        'seller_phone' => array(
                            'title' => __('Номер телефона', 'cdek'),
                            'type' => 'text',
                            'default' => __('+79969633817', 'cdek')
                        ),

                        'rate' => array(
                            'title' => __('Тарифы', 'cdek'),
                            'type' => 'multiselect',
                            'options' => Tariff::getTariffList()),

                        'mode' => array(
                            'title' => __('Место отправки', 'cdek'),
                            'type' => 'select',
                            'options' => ['От двери', 'От склада']
                        ),

                        'city' => array(
                            'title' => __('Город отправления', 'cdek'),
                            'type' => 'text',
                            'default' => __('Москва', 'cdek')
                        ),

                        'street' => array(
                            'title' => __('Адрес', 'cdek'),
                            'type' => 'text',
                            'default' => __('Ленина 21 42', 'cdek')
                        ),

                        'pvz_info' => array(
                            'type' => 'text',
                            'placeholder' => 'Выбрать пвз на карте'
                        ),

                        'pvz_code' => array(
                            'type' => 'hidden',
                        ),

                        'map' => array(
                            'type' => 'hidden',
                        ),

                        'city_code_value' => array(
                            'type' => 'text',
                            'css' => 'display: none;',
                            'default' => __('44', 'cdek')
                        ),

                    );

                }

                public function calculate_shipping($package = [])
                {
                    $totalWeight = 0;
                    foreach ($package['contents'] as $productGroup) {
                        $quantity = $productGroup['quantity'];
                        $weight = $productGroup['data']->get_weight();
                        $totalWeight += $quantity * (int) $weight;
                    }

                    $cdekShipping = WC()->shipping->load_shipping_methods()['cdek'];
                    $cdekShippingSettings = $cdekShipping->settings;
                    $tariffList = $cdekShippingSettings['rate'];
                    $tariffType = $cdekShippingSettings['mode'];
                    $city = $package["destination"]['city'];
                    $postcode = $package["destination"]['postcode'];

                    $availableTariff = [];
                    foreach ($tariffList as $tariff) {
                        $code = Tariff::getTariffCodeType($tariff, $tariffType);
                        if (!empty($code)) {
                            $availableTariff[] = $code;
                        }
                    }


                    if ($city) {
                        foreach ($availableTariff as $tariff) {
                            $delivery = json_decode(cdekApi()->calculateWP($city, $postcode, $totalWeight, $tariff));

                            if (empty($delivery->errors) && $delivery->delivery_sum !== null){
                                $rate = array(
                                    'id' => $this->id . '_' . $tariff,
                                    'label' => 'CDEK: ' . Tariff::getTariffNameByCode($tariff) . ', (' . $delivery->period_min . '-' . $delivery->period_max . ' дней)',
                                    'cost' => $delivery->delivery_sum,
                                    'meta_data' => ['type' => Tariff::getTariffTypeToByCode($tariff)],
                                    'className' => 'asdasd',
                                    'class' => 'asdasd',
                                );
                                $this->add_rate($rate);
                            }
                        }
                    }
                }
            }
        }
    }

    function cdek_map_display($shippingMethod)
    {
        $current = WC()->session->get( 'chosen_shipping_methods')[0];
        if ($shippingMethod->get_method_id() === 'cdek' &&
            $shippingMethod->get_meta_data()['type'] === '1' &&
            $shippingMethod->get_id() === $current &&
            $_SERVER['REQUEST_URI'] !== '/cart/') {
            include 'templates/public/open-map.php';
        }
    }

    function cdek_checkout_shipping()
    {
        include 'templates/public/map.php';
    }

    add_action('woocommerce_checkout_process', 'is_pvz_code');

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

    add_action('woocommerce_shipping_init', 'cdek_shipping_method');
    add_action('woocommerce_after_shipping_rate', 'cdek_map_display', 10, 2);
    add_action('woocommerce_checkout_shipping', 'cdek_checkout_shipping');
    add_filter( 'woocommerce_new_order' , 'cdek_woocommerce_new_order_action', 10, 2);
    add_filter( 'woocommerce_shipping_methods', 'add_cdek_shipping_method' );

    function add_cdek_shipping_method($methods)
    {
        $methods[] = 'Cdek_Shipping_Method';
        return $methods;
    }

    add_filter('woocommerce_admin_order_data_after_shipping_address', 'cdek_admin_order_data_after_shipping_address');

    function cdek_admin_order_data_after_shipping_address ($order)
    {
        wp_enqueue_script('cdek-create-order', plugin_dir_url(__FILE__) . 'assets/js/create-order.js', array('jquery'), '3.x', true);
        $orderUuid = $order->get_meta('cdek_order_uuid');
        include 'templates/admin/create-order.php';
    }
}
