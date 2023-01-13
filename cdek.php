<?php
/**
 * Plugin Name:       CDEKDelivery
 * Plugin URI:        https://www.cdek.ru/ru/integration/modules/33
 * Description:       Интеграция доставки CDEK
 * Version:           1.0
 * Requires at least: 6.0
 * Requires PHP:      7.2
 * Author:            CDEK IT
 * WC requires at least: 6.0
 * WC tested up to: 7.0
 */

use Cdek\CdekApi;
use Cdek\Helper;
use Cdek\Model\Tariff;
use Cdek\WeightCalc;

if (!function_exists('add_action')) {
    exit();
}

require 'vendor/autoload.php';

add_action('rest_api_init', 'cdek_register_route');
add_filter('woocommerce_admin_order_data_after_shipping_address', 'cdek_admin_order_data_after_shipping_address');
add_filter('woocommerce_new_order', 'cdek_woocommerce_new_order_action', 10, 2);
add_filter('woocommerce_shipping_methods', 'add_cdek_shipping_method');
add_action('woocommerce_shipping_init', 'cdek_shipping_method');
add_action('woocommerce_after_shipping_rate', 'cdek_map_display', 10, 2);
add_action('woocommerce_checkout_process', 'is_pvz_code');
add_action('wp_enqueue_scripts', 'cdek_widget_enqueue_script');
add_action('admin_enqueue_scripts', 'cdek_admin_enqueue_script');
add_filter('woocommerce_update_order_review_fragments', 'cdek_add_update_form_billing', 99);
add_filter('woocommerce_checkout_fields', 'cdek_override_checkout_fields', 30);
add_action('wp_footer', 'cdek_add_script_update_shipping_method');
//add_filter('woocommerce_billing_fields', 'cdek_woocommerce_billing_fields', 30, 2);

function cdek_woocommerce_billing_fields($fields)
{
    if (!array_key_exists('billing_city', $fields)) {
        $fields['billing_city'] = [
            'label' => 'Населённый пункт',
            'placeholder' => '',
            'class' => [
                "form-row-wide",
                "address-field"
            ],
            'required' => true,
            'public' => true,
            'payment_method' => ["0"],
            'shipping_method' => ["0"],
            'order' => 16,
            'priority' => 16,
        ];
    }

//    if (array_key_exists('billing_state', $fields)) {
//        $fields['billing_state'] = [];
//    }
//
//    if (array_key_exists('billing_address_line_1', $fields)) {
//        $fields['billing_address_line_1'] = [];
//    }
    return $fields;
}

function cdek_widget_enqueue_script()
{
    if (is_checkout()) {
        wp_enqueue_style('cdek-css-leaflet', plugin_dir_url(__FILE__) . 'assets/css/leaflet.css');
        wp_enqueue_script('cdek-css-leaflet-min', plugin_dir_url(__FILE__) . 'assets/js/lib/leaflet-src.min.js');
        wp_enqueue_style('cdek-css', plugin_dir_url(__FILE__) . 'assets/css/cdek-map-v2.css');
        wp_enqueue_style('cdek-admin-leaflet-cluster-default', plugin_dir_url(__FILE__) . 'assets/css/MarkerCluster.Default.min.css');
        wp_enqueue_style('cdek-admin-leaflet-cluster', plugin_dir_url(__FILE__) . 'assets/css/MarkerCluster.min.css');
        wp_enqueue_script('cdek-admin-leaflet-cluster', plugin_dir_url(__FILE__) . 'assets/js/lib/leaflet.markercluster-src.min.js');
        wp_enqueue_script('cdek-map', plugin_dir_url(__FILE__) . 'assets/js/map-v4.js', array('jquery'), '1.7.0', true);
        addYandexMap();
    }
}

function cdek_admin_enqueue_script()
{
    wp_enqueue_script('cdek-admin-delivery', plugin_dir_url(__FILE__) . 'assets/js/delivery-v4.js', array('jquery'), '1.7.0', true);
    wp_enqueue_script('cdek-admin-leaflet', plugin_dir_url(__FILE__) . 'assets/js/lib/leaflet-src.min.js');
    wp_enqueue_script('cdek-admin-leaflet-cluster', plugin_dir_url(__FILE__) . 'assets/js/lib/leaflet.markercluster-src.min.js');
    wp_enqueue_style('cdek-admin-leaflet', plugin_dir_url(__FILE__) . 'assets/css/leaflet.css');
    wp_enqueue_style('cdek-admin-leaflet-cluster-default', plugin_dir_url(__FILE__) . 'assets/css/MarkerCluster.Default.min.css');
    wp_enqueue_style('cdek-admin-leaflet-cluster', plugin_dir_url(__FILE__) . 'assets/css/MarkerCluster.min.css');
    wp_enqueue_style('cdek-admin-delivery', plugin_dir_url(__FILE__) . 'assets/css/delivery-v3.css');
    addYandexMap();
}

function addYandexMap()
{
    $cdekShippingSettings = Helper::getSettingDataPlugin();
    if (array_key_exists('yandex_map_api_key', $cdekShippingSettings) && $cdekShippingSettings['yandex_map_api_key'] !== '') {
        $WP_Http = new WP_Http();
        $resp = $WP_Http->request('https://api-maps.yandex.ru/2.1?apikey=' . $cdekShippingSettings['yandex_map_api_key'] . '&lang=ru_RU', [
            'method' => 'GET',
            'headers' => [
                "Content-Type" => "application/json",
            ],
        ]);

        if ($resp['response']['code'] === 200) {
            wp_enqueue_script('cdek-admin-yandex-api', 'https://api-maps.yandex.ru/2.1?apikey=' . $cdekShippingSettings['yandex_map_api_key'] . '&lang=ru_RU');
            wp_enqueue_script('cdek-admin-leaflet-yandex', plugin_dir_url(__FILE__) . 'assets/js/lib/Yandex.js');
        } else {
            $setting = WC()->shipping->load_shipping_methods()['official_cdek'];
            $setting->update_option('yandex_map_api_key', '');
            $setting->update_option('map_layer', '1');
        }


    } else {
        $cdekShippingSettings['map_layer'] = '0';
    }
}

function cdek_register_route()
{
    register_rest_route('cdek/v1', '/check-auth', array(
        'methods' => 'GET',
        'callback' => 'check_auth',
        'permission_callback' => '__return_true'
    ));

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

    register_rest_route('cdek/v1', '/set-pvz-code-tmp', array(
        'methods' => 'GET',
        'callback' => 'set_pvz_code_tmp',
        'permission_callback' => '__return_true'
    ));

}

function create_order($data)
{
    $api = new CdekApi();

    if (!$api->checkAuth()) {
        return json_encode(['state' => 'error', 'message' => 'Ошибка авторизации. Проверьте идентификатор и секретный ключ клиента в настройках плагина CDEKDelivery']);
    }

    $param = [];
    $orderId = $data->get_param('package_order_id');
    $param = setPackage($data, $orderId, $param);
    $order = wc_get_order($orderId);

    $postOrderData = get_post_meta($orderId, 'order_data');

    $pvzCode = $postOrderData[0]['pvz_code'];
    $tariffId = $postOrderData[0]['tariff_id'];
    $cityCode = $postOrderData[0]['city_code'];
    $cityName = $order->get_shipping_city();
    $cityAddress = $order->get_shipping_address_1();

    if (empty($cityCode)) {
        $cityName = $order->get_shipping_city();
        $stateName = $order->get_shipping_state();
        $cityCode = $api->getCityCodeByCityName($cityName, $stateName);
        if ($cityCode === -1) {
            return json_encode(['state' => 'error', 'message' => 'Ошибка. Не удалось найти город отправки']);
        }
    }

    if ((int)Tariff::getTariffTypeToByCode($tariffId)) {
        $param['delivery_point'] = $pvzCode;
    } else {
        $param['to_location'] = [
            'code' => $cityCode,
            'city' => $cityName,
            'address' => $cityAddress
        ];
    }

    $param['recipient'] = [
        'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        'phones' => [
            'number' => $order->get_billing_phone()
        ]
    ];
    $param['tariff_code'] = $tariffId;
    $param['print'] = 'waybill';

    $cdekShippingSettings = Helper::getSettingDataPlugin();
    $services = $cdekShippingSettings['service_list'];

    $selectedPaymentMethodId = $order->get_payment_method();
    if ($selectedPaymentMethodId === 'cod') {
        $param['delivery_recipient_cost_adv'] = [
            'sum' => $order->get_shipping_total(),
            'threshold' => (int)$cdekShippingSettings['stepcodprice']
        ];
    }

    if ($services !== "") {
        $servicesListForParam = [];
        foreach ($services as $service) {
            if ($service === 'DELIV_RECEIVER' && $tariffId == '62') {
                $servicesListForParam['code'] = $service;
            }
        }
        $param['services'] = $servicesListForParam;
    }

    $orderDataJson = $api->createOrder($param);
    $orderData = json_decode($orderDataJson);

    if ($orderData->requests[0]->state === 'INVALID') {
        return json_encode(['state' => 'error', 'message' => 'Ошибка. Заказ не создан. (' . $orderData->requests[0]->errors[0]->message . ')']);
    }

    $code = $orderData->entity->uuid;
    $orderInfoJson = $api->getOrder($code);
    $orderInfo = json_decode($orderInfoJson);
    $cdekNumber = null;
    if (property_exists($orderInfo->entity, 'cdek_number')) {
        $cdekNumber = $orderInfo->entity->cdek_number;
    }

    if (empty($cdekNumber)) {
        $cdekNumber = $code;
    }

    $postOrderData[0]['cdek_order_uuid'] = $cdekNumber;
    $postOrderData[0]['cdek_order_waybill'] = $orderData->entity->uuid;
    update_post_meta($orderId, 'order_data', $postOrderData[0]);

    return json_encode(['state' => 'success', 'code' => $cdekNumber, 'waybill' => '/wp-json/cdek/v1/get-waybill?number=' . $orderData->entity->uuid]);
}

function setPackage($data, $orderId, array $param)
{
    $cdekShippingSettings = Helper::getSettingDataPlugin();
    if ($cdekShippingSettings['has_packages_mode'] === 'yes') {
        $packageData = json_decode($data->get_param('package_data'));
        $param['packages'] = get_packages($orderId, $packageData);
    } else {
        $length = $data->get_param('package_length');
        $width = $data->get_param('package_width');
        $height = $data->get_param('package_height');
        $order = wc_get_order($orderId);
        $items = $order->get_items();
        $itemsData = [];
        $totalWeight = 0;
        foreach ($items as $key => $item) {
            $product = $item->get_product();
            $weight = $product->get_weight();
            $weightClass = new WeightCalc();
            $weight = $weightClass->getWeight($weight);
            $quantity = (int)$item->get_quantity();
            $totalWeight += $quantity * $weight;
            $cost = $product->get_price();

            $selectedPaymentMethodId = $order->get_payment_method();
            if ($selectedPaymentMethodId === 'cod') {
                $paymentValue = (int) (((int)$cdekShippingSettings['percentcod'] / 100) * $cost);
            } else {
                $paymentValue = 0;
            }

            $itemsData[] = [
                "ware_key" => $product->get_id(),
                "payment" => ["value" => $paymentValue],
                "name" => $product->get_name(),
                "cost" => $cost,
                "amount" => $item->get_quantity(),
                "weight" => $weight,
                "weight_gross" => $weight + 1,
            ];
        }

        $param['packages'] = [
            'number' => $orderId,
            'length' => $length,
            'width' => $width,
            'height' => $height,
            'weight' => $totalWeight,
            'items' => $itemsData
        ];
    }
    return $param;
}

function get_packages($orderId, $packageData)
{
    $result = [];
    foreach ($packageData as $key => $package) {
        $data = get_package_items($package->items, $orderId, $key);
        $result[] = [
            'number' => $orderId . '_' . Helper::generateRandomString(5),
            'length' => $package->length,
            'width' => $package->width,
            'height' => $package->height,
            'weight' => $data['weight'],
            'items' => $data['items']
        ];
    }

    return $result;
}

function get_package_items($items, $orderId, $key)
{
    $itemsData = [];
    $totalWeight = 0;
    foreach ($items as $item) {
        $product = wc_get_product($item[0]);
        $weight = $product->get_weight();
        $weightClass = new WeightCalc();
        $weight = $weightClass->getWeight($weight);
        $totalWeight += (int)$item[2] * $weight;

        $order = wc_get_order($orderId);
        $selectedPaymentMethodId = $order->get_payment_method();
        if ($selectedPaymentMethodId === 'cod') {
            $paymentValue = $product->get_price();
        } else {
            $paymentValue = 0;
        }

        $itemsData[] = [
            "ware_key" => $product->get_id(),
            "payment" => ["value" => $paymentValue],
            "name" => $product->get_name(),
            "cost" => $product->get_price(),
            "amount" => $item[2],
            "weight" => $weight,
            "weight_gross" => $weight + 1,
        ];
    }
    return ['items' => $itemsData, 'weight' => $totalWeight];
}

function get_waybill($data)
{
    $api = new CdekApi();
    $waybillData = $api->createWaybill($data->get_param('number'));
    $waybill = json_decode($waybillData);

    if ($waybill->requests[0]->state === 'INVALID' || property_exists($waybill->requests[0], 'errors')) {
        echo '
        Не удалось создать квитанцию. 
        Для решения проблемы, попробуй пересоздать заказ. Нажмите кнопку "Отменить"
        и введите габариты упаковки повторно.';
        exit();
    }

    $order = json_decode($api->getOrder($data->get_param('number')));
    foreach ($order->related_entities as $entity) {
        if ($entity->uuid === $waybill->entity->uuid) {
            $result = $api->getWaybillByLink($entity->url);
            header("Content-type:application/pdf");
            echo $result;
            exit();
        }
    }

    $result = $api->getWaybillByLink(end($order->related_entities)->url);
    header("Content-type:application/pdf");
    echo $result;
    exit();
}

function check_auth()
{
    $api = new CdekApi();
    $check = $api->checkAuth();
    if ($check) {
        update_option('cdek_auth_check', '1');
    } else {
        update_option('cdek_auth_check', '0');
    }
    return json_encode(['state' => $check]);
}

function get_region($data)
{
    $api = new CdekApi();
    return $api->getRegion($data->get_param('city'));
}

function set_pvz_code_tmp($data)
{
    delete_post_meta(-1, 'pvz_code_tmp');
    $pvzCode = $data->get_param('pvz_code');
    $pvzInfo = $data->get_param('pvz_info');
    $cityCode = $data->get_param('city_code');
    update_post_meta(-1, 'pvz_code_tmp', ['pvz_code' => $pvzCode, 'pvz_info' => $pvzInfo, 'city_code' => $cityCode]);
}

function get_city_code($data)
{
    $api = new CdekApi();
    return $api->getCityCodeByCityName($data->get_param('city_name'), $data->get_param('state_name'));
}

function get_pvz($data)
{
    $api = new CdekApi();
    if ($data->get_param('admin')) {
        return $api->getPvz($data->get_param('city_code'), true);
    }
    return $api->getPvz($data->get_param('city_code'));
}

function delete_order($data)
{
    $api = new CdekApi();
    $orderId = $data->get_param('order_id');
    $postOrderData = get_post_meta($orderId, 'order_data');
    $postOrderData[0]['cdek_order_uuid'] = '';
    update_post_meta($orderId, 'order_data', $postOrderData[0]);

    return $api->deleteOrder($data->get_param('number'));
}

function cdek_shipping_method()
{
    if (!class_exists('CdekShippingMethod')) {
        class CdekShippingMethod extends \Cdek\CdekShippingMethod
        {
        }
    }
}

function cdek_map_display($shippingMethodCurrent)
{
    if (is_checkout() && isTariffTypeFromStore($shippingMethodCurrent)) {
        $cdekShippingSettings = Helper::getSettingDataPlugin();
        $layerMap = $cdekShippingSettings['map_layer'];
        if ($cdekShippingSettings['yandex_map_api_key'] === "") {
            $layerMap = "0";
        }

        $postamat = (int)isPostamatOrStore();

        include 'templates/public/open-map.php';
    }
}

function isTariffTypeFromStore($shippingMethodCurrent)
{
    if ($shippingMethodCurrent->get_method_id() !== 'official_cdek') {
        return false;
    }

    $shippingMethodIdSelected = WC()->session->get('chosen_shipping_methods')[0];

    if ($shippingMethodCurrent->get_id() !== $shippingMethodIdSelected) {
        return false;
    }

    $tariffCode = explode('_', $shippingMethodIdSelected)[2];
    return (bool)(int)Tariff::getTariffTypeToByCode($tariffCode);
}

function isPostamatOrStore()
{
    $shippingMethodIdSelected = WC()->session->get('chosen_shipping_methods')[0];
    $tariffCode = explode('_', $shippingMethodIdSelected)[2];
    return Tariff::isTariffEndPointPostamatByCode($tariffCode);
}

function cdek_add_update_form_billing($fragments)
{

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

function getShipToDestination()
{
    $shipToDestination = get_option( 'woocommerce_ship_to_destination' );
    if ($shipToDestination === 'billing_only') {
        return 'billing';
    }
    return $shipToDestination;
}

function cdek_override_checkout_fields($fields)
{
//    $chosen_methods = WC()->session->get('chosen_shipping_methods');
//
//    if (!$chosen_methods || $chosen_methods[0] === false) {
//        return $fields;
//    }
//
//    $output_array = [];
//    preg_match('/official_cdek/', $chosen_methods[0], $output_array);

//    $shippingMethodArray = explode('_', $chosen_methods[0]);

//    if (!isset($fields['billing']['billing_phone'])) {
//        $fields['billing']['billing_phone'] = [
//            'label' => 'Телефон',
//            'required' => true,
//            'class' => ['form-row-wide'],
//            'validate' => ['phone'],
//            'autocomplete' => 'tel',
//            'priority' => 100
//        ];
//    }

    $shipToDestination = getShipToDestination();

    if ($shipToDestination === 'billing') {
        if (!isset($fields['billing']['billing_first_name'])) {
            $fields['billing']['billing_first_name'] = [
                'label' => 'Имя',
                'placeholder' => '',
                'class' => [0 => 'form-row-first',],
                'required' => true,
                'public' => true,
                'payment_method' => [0 => '0',],
                'shipping_method' => [0 => '0',],
                'priority' => 10,
            ];
        }

        if (!isset($fields['billing']['billing_last_name'])) {
            $fields['billing']['billing_last_name'] = [
                'label' => 'Фамилия',
                'placeholder' => '',
                'class' => [0 => 'form-row-last',],
                'required' => true,
                'public' => true,
                'payment_method' => [0 => '0',],
                'shipping_method' => [0 => '0',],
                'priority' => 11,
            ];
        }

        if (!isset($fields['billing']['billing_city'])) {
            $fields['billing']['billing_city'] = [
                'label' => 'Населённый пункт',
                'required' => true,
                'class' => ['form-row-wide', 'address-field'],
                'autocomplete' => 'address-level2',
                'priority' => 16
            ];
        }

        if (!isset($fields['billing']['billing_state'])) {
            $fields['billing']['billing_state'] = [
                'label' => 'Область / район',
                'required' => true,
                'class' => ['form-row-wide', 'address-field'],
                'validate' => ['state'],
                'autocomplete' => 'address-level1',
                'priority' => 17,
                'country_field' => "billing_country",
            ];
        }

        if (!isset($fields['billing']['billing_address_1'])) {
            $fields['billing']['billing_address_1'] = [
                'label' => 'Адрес',
                'placeholder' => 'Номер дома и название улицы',
                'class' => ['form-row-wide', 'address-field'],
                'autocomplete' => 'address-line1',
                'priority' => 18
            ];
        }

        if (!isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone'] = [
                'label' => 'Телефон',
                'placeholder' => '',
                'type' => 'tel',
                'required' => true,
                'public' => true,
                'class' => ['form-row-wide'],
                'autocomplete' => 'address-line1',
                'priority' => 19
            ];
        }
    } else {

        if (!isset($fields['billing']['billing_first_name'])) {
            $fields['billing']['billing_first_name'] = [
                'label' => 'Имя',
                'placeholder' => '',
                'class' => [0 => 'form-row-first',],
                'required' => true,
                'public' => true,
                'payment_method' => [0 => '0',],
                'shipping_method' => [0 => '0',],
                'priority' => 10,
            ];
        }

        if (!isset($fields['billing']['billing_last_name'])) {
            $fields['billing']['billing_last_name'] = [
                'label' => 'Фамилия',
                'placeholder' => '',
                'class' => [0 => 'form-row-last',],
                'required' => true,
                'public' => true,
                'payment_method' => [0 => '0',],
                'shipping_method' => [0 => '0',],
                'priority' => 11,
            ];
        }

        if (!isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone'] = [
                'label' => 'Телефон',
                'placeholder' => '',
                'type' => 'tel',
                'required' => true,
                'public' => true,
                'class' => ['form-row-wide'],
                'autocomplete' => 'address-line1',
                'priority' => 19
            ];
        }

//        if (!isset($fields['shipping']['shipping_first_name'])) {
//            $fields['shipping']['shipping_first_name'] = [
//                'label' => 'Имя',
//                'placeholder' => '',
//                'class' => [0 => 'form-row-first',],
//                'required' => true,
//                'public' => true,
//                'payment_method' => [0 => '0',],
//                'shipping_method' => [0 => '0',],
//                'priority' => 10,
//            ];
//        }
//
//        if (!isset($fields['shipping']['shipping_last_name'])) {
//            $fields['shipping']['shipping_last_name'] = [
//                'label' => 'Фамилия',
//                'placeholder' => '',
//                'class' => [0 => 'form-row-last',],
//                'required' => true,
//                'public' => true,
//                'payment_method' => [0 => '0',],
//                'shipping_method' => [0 => '0',],
//                'priority' => 11,
//            ];
//        }

        if (!isset($fields['shipping']['shipping_city'])) {
            $fields['shipping']['shipping_city'] = [
                'label' => 'Населённый пункт',
                'required' => true,
                'class' => ['form-row-wide', 'address-field'],
                'autocomplete' => 'address-level2',
                'priority' => 16
            ];
        }

        if (!isset($fields['shipping']['shipping_state'])) {
            $fields['shipping']['shipping_state'] = [
                'label' => 'Область / район',
                'required' => true,
                'class' => ['form-row-wide', 'address-field'],
                'validate' => ['state'],
                'autocomplete' => 'address-level1',
                'priority' => 17,
                'country_field' => "billing_country",
            ];
        }

        if (!isset($fields['shipping']['shipping_address_1'])) {
            $fields['shipping']['shipping_address_1'] = [
                'label' => 'Адрес',
                'placeholder' => 'Номер дома и название улицы',
                'class' => ['form-row-wide', 'address-field'],
                'autocomplete' => 'address-line1',
                'priority' => 18
            ];
        }
    }

    return $fields;
}

function cdek_add_script_update_shipping_method()
{
    if (is_checkout()) {
        ?>
        <script>
            jQuery(document).on('change', 'input[name="shipping_method[0]"]', function () {
                jQuery(document.body).trigger('update_checkout')
            });
        </script>
        <?php
    }
}

function is_pvz_code()
{
    $shippingMethodIdSelected = WC()->session->get('chosen_shipping_methods')[0];

    if (strpos($shippingMethodIdSelected, 'official_cdek') !== false) {
        $tariffCode = getTariffCodeByShippingMethodId($shippingMethodIdSelected);
        if (checkTariffFromStoreByTariffCode($tariffCode)) {
            $pvzCode = $_POST['pvz_code'];
            if (empty($pvzCode)) {
                $pvzCodeTmp = get_post_meta(-1, 'pvz_code_tmp');
                if (empty($pvzCodeTmp[0]['pvz_code'])) {
                    wc_add_notice(__('Не выбран пункт выдачи заказа.'), 'error');
                } else {
                    $_POST['pvz_code'] = $pvzCodeTmp[0]['pvz_code'];
                    $_POST['pvz_address'] = $pvzCodeTmp[0]['pvz_address'];
                    $_POST['city_code'] = $pvzCodeTmp[0]['city_code'];
                    delete_post_meta(-1, 'pvz_code_tmp');
                }
            }
        } else {
            $shipToDestination = getShipToDestination();
            if ($shipToDestination === 'billing') {
                if(array_key_exists('billing_address_1', $_POST)) {
                    if (empty($_POST['billing_address_1'])) {
                        wc_add_notice(__('Нет адреса отправки.'), 'error');
                    }
                }
            } else {
                if(array_key_exists('shipping_address_1', $_POST)) {
                    if (empty($_POST['shipping_address_1'])) {
                        wc_add_notice(__('Нет адреса отправки.'), 'error');
                    }
                }
            }
        }
    }
}

function getTariffCodeByShippingMethodId($shippingMethodId)
{
    return explode('_', $shippingMethodId)[2];
}

function checkTariffFromStoreByTariffCode($tariffCode)
{
    return (bool)(int)Tariff::getTariffTypeToByCode($tariffCode);
}

function cdek_woocommerce_new_order_action($order_id, $order)
{
    if (isCdekShippingMethod($order)) {
        $pvzInfo = $_POST['pvz_info'];
        $pvzCode = $_POST['pvz_code'];
        $tariffId = getTariffCodeCdekShippingMethodByOrder($order);
        $cityCode = $_POST['city_code'];

        if ($pvzInfo !== null) {
            $api = new CdekApi();
            $cityData = $api->getCityByCode($cityCode);
            $order->set_shipping_address_1($pvzInfo);
            $order->set_shipping_city($cityData['city']);
            $order->set_shipping_state($cityData['region']);
            $order->save();

            $shippingMethodArray = $order->get_items('shipping');
            $shippingMethod = array_shift($shippingMethodArray);
            $shippingMethod->add_meta_data('pvz', $pvzCode . ' (' . $pvzInfo . ')');
            $shippingMethod->save_meta_data();
        }

        add_post_meta($order_id, 'order_data', [
            'pvz_address' => $pvzInfo,
            'pvz_code' => $pvzCode,
            'tariff_id' => $tariffId,
            'city_code' => $cityCode
        ]);
    }

}

function add_cdek_shipping_method($methods)
{
    $methods['official_cdek'] = 'CdekShippingMethod';
    return $methods;
}

function cdek_admin_order_data_after_shipping_address($order)
{
    if (isCdekShippingMethod($order)) {
        $api = new CdekApi();
        if ($api->checkAuth()) {
            $orderId = $order->get_id();
            $postOrderData = get_post_meta($orderId, 'order_data');
            $orderUuid = $postOrderData[0]['cdek_order_uuid'] ?? '';
            $tariffId = $postOrderData[0]['tariff_id'];
            if (!empty($orderUuid) || !empty($tariffId)) {
                $waybill = $postOrderData[0]['cdek_order_waybill'] ?? '';
                $items = [];
                foreach ($order->get_items() as $item) {
                    $items[$item['product_id']] = ['name' => $item['name'], 'quantity' => $item['quantity']];
                }

                $cdekShippingSettings = Helper::getSettingDataPlugin();
                $hasPackages = false;
                if ($cdekShippingSettings['has_packages_mode'] === 'yes') {
                    $hasPackages = true;
                }

                include 'templates/admin/create-order.php';
            }
        } else {
            echo 'Авторизация не пройдена. Введите корректные идентификатор и секретный ключ клиента в настройках плагина CDEKDelivery';
        }
    }

}

function getTariffCodeCdekShippingMethodByOrder($order)
{
    $shippingMethodArray = $order->get_items('shipping');
    $shippingMethod = array_shift($shippingMethodArray);
    return $shippingMethod->get_meta('tariff_code');
}

function isCdekShippingMethod($order)
{
    $shippingMethodArray = $order->get_items('shipping');
    if (empty($shippingMethodArray)) {
        return false;
    }
    $shippingMethod = array_shift($shippingMethodArray);
    $shippingMethodId = $shippingMethod->get_method_id();
    if ($shippingMethodId === 'official_cdek') {
        return true;
    }
    return false;
}

