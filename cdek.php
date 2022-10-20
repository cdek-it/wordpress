<?php
/**
 * Plugin Name:       CDEKDelivery
 * Plugin URI: https://www.cdek.ru/ru/integration/modules/33
 * Description:       Интеграция доставки CDEK
 * Version:           1.0
 * Requires at least: 6.0
 * Requires PHP:      7.2
 * Author:            Klementev Ilya
 * WC requires at least: 6.0
 * WC tested up to: 7.0
 */

use Cdek\CdekApi;
use Cdek\Model\SettingData;
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
add_filter( 'woocommerce_update_order_review_fragments', 'cdek_add_update_form_billing', 99 );
add_filter('woocommerce_checkout_fields', 'cdek_override_checkout_fields');
add_action('wp_footer', 'cdek_add_script_update_shipping_method');

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
    $cdekShippingSettings = getSettingDataPlugin();
    if ($cdekShippingSettings['apikey'] !== '') {
        $WP_Http = new WP_Http();
        $resp = $WP_Http->request( 'https://api-maps.yandex.ru/2.1?apikey=' . $cdekShippingSettings['apikey'] . '&lang=ru_RU', [
            'method' => 'GET',
            'headers' => [
                "Content-Type" => "application/json",
            ],
        ] );

        if ($resp['response']['code'] === 200) {
            wp_enqueue_script('cdek-admin-yandex-api', 'https://api-maps.yandex.ru/2.1?apikey=' . $cdekShippingSettings['apikey'] . '&lang=ru_RU');
            wp_enqueue_script('cdek-admin-leaflet-yandex', plugin_dir_url(__FILE__) . 'assets/js/lib/Yandex.js');
        } else {
            $setting = WC()->shipping->load_shipping_methods()['official_cdek'];
            $setting->update_option('apikey', '');
            $setting->update_option('tiles', '1');
        }


    } else {
        $cdekShippingSettings['tiles'] = '0';

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

function generateRandomString($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function getStateAuth()
{
    $cdekShippingSettings = getSettingDataPlugin();
    $clientId = $cdekShippingSettings['client_id'];
    $clientSecret = $cdekShippingSettings['client_secret'];
    $response = CdekApi()->checkAuth($clientId, $clientSecret);
    $stateAuth = json_decode($response);
    return $stateAuth->state;
}

function create_order($data)
{
    if (!getStateAuth()) {
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
        $cityCode = CdekApi()->getCityCodeByCityName($cityName, $stateName);
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
        'name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
        'phones' => [
            'number' => $order->get_billing_phone()
        ]
    ];
    $param['tariff_code'] = $tariffId;
    $param['print'] = 'waybill';

    $cdekShippingSettings = getSettingDataPlugin();
    $services = $cdekShippingSettings['service'];

    if ($services !== "") {
        $servicesListForParam = [];
        foreach ($services as $service) {
            if ($service === 'DELIV_RECEIVER' && $tariffId == '62') {
                $servicesListForParam['code'] = $service;
            }
        }
        $param['services'] = $servicesListForParam;
    }



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

    $postOrderData[0]['cdek_order_uuid'] = $cdekNumber;
    $postOrderData[0]['cdek_order_waybill'] = $orderData->entity->uuid;
    update_post_meta($orderId, 'order_data', $postOrderData[0]);

//    $order->update_meta_data('cdek_order_uuid', $cdekNumber);
//    $order->update_meta_data('cdek_order_waybill', $orderData->entity->uuid);
//    $order->save_meta_data();
    return json_encode(['state' => 'success', 'code' => $cdekNumber, 'waybill' => '/wp-json/cdek/v1/get-waybill?number=' . $orderData->entity->uuid]);
}

function setPackage($data, $orderId, array $param)
{
    $cdekShippingSettings = getSettingDataPlugin();
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
        $totalWeight = 0;
        foreach ($items as $item) {
            $product = $item->get_product();
            $weight = $product->get_weight();
            $weightClass = new WeightCalc();
            $weight = $weightClass->getWeight($weight);
            $quantity = (int)$item->get_quantity();
            $totalWeight += $quantity * $weight;

            $itemsData[] = [
                "ware_key" => $product->get_id(),
                "payment" => ["value" => 0],
                "name" => $product->get_name(),
                "cost" => $product->get_price(),
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
    foreach ($packageData as $package) {
        $data = get_package_items($package->items);
        $result[] = [
            'number' => $orderId . '_' . generateRandomString(5),
            'length' => $package->length,
            'width' => $package->width,
            'height' => $package->height,
            'weight' => $data['weight'],
            'items' => $data['items']
        ];
    }

    return $result;
}

function get_package_items($items)
{
    $itemsData = [];
    $totalWeight = 0;
    foreach ($items as $item) {
        $product = wc_get_product($item[0]);
        $weight = $product->get_weight();
        $weightClass = new WeightCalc();
        $weight = $weightClass->getWeight($weight);
        $totalWeight += (int)$item[2] * $weight;
        $itemsData[] = [
            "ware_key" => $product->get_id(),
            "payment" => ["value" => 0],
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
    $waybillData = CdekApi()->createWaybill($data->get_param('number'));
    $waybill = json_decode($waybillData);

    if ($waybill->requests[0]->state === 'INVALID' || property_exists($waybill->requests[0], 'errors')) {
        echo '
        Не удалось создать квитанцию. 
        Для решения проблемы, попробуй пересоздать заказ. Нажмите кнопку "Отменить"
        и введите габариты упаковки повторно.';
        exit();
    }

    $order = json_decode(CdekApi()->getOrder($data->get_param('number')));
    foreach ($order->related_entities as $entity) {
        if ($entity->uuid === $waybill->entity->uuid) {
            $result = CdekApi()->getWaybillByLink($entity->url);
            header("Content-type:application/pdf");
            echo $result;
            exit();
        }
    }

    $result = CdekApi()->getWaybillByLink(end($order->related_entities)->url);
    header("Content-type:application/pdf");
    echo $result;
    exit();
}

function check_auth($data)
{
    $response = CdekApi()->checkAuth($data->get_param('client_id'), $data->get_param('client_secret'));
    $stateAuth = json_decode($response);
    if ($stateAuth->state) {
        update_option('cdek_auth_check', '1');
    } else {
        update_option('cdek_auth_check', '0');
    }
    return $response;
}

function get_region($data)
{
    return CdekApi()->getRegion($data->get_param('city'));
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
    return CdekApi()->getCityCodeByCityName($data->get_param('city_name'), $data->get_param('state_name'));
}

function get_pvz($data)
{
    return cdekApi()->getPvz($data->get_param('city_code'));
}

function delete_order($data)
{
    $orderId = $data->get_param('order_id');
    $postOrderData = get_post_meta($orderId, 'order_data');
    $postOrderData[0]['cdek_order_uuid'] = '';
    update_post_meta($orderId, 'order_data', $postOrderData[0]);

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
    $cdekShippingSettings = getSettingDataPlugin();
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

function cdek_map_display($shippingMethodCurrent)
{
    if (is_checkout() && isTariffTypeFromStore($shippingMethodCurrent)) {
        $cdekShippingSettings = getSettingDataPlugin();
        $layerMap = $cdekShippingSettings['tiles'];
        if ($cdekShippingSettings['apikey'] === "") {
            $layerMap = "0";
        }

        $postamat = (int)isPostamatOrStore();

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
    return (bool)(int)Tariff::getTariffTypeToByCode($tariffCode);
}

function isPostamatOrStore() {
    $shippingMethodIdSelected = WC()->session->get('chosen_shipping_methods')[0];
    $tariffCode = explode('_', $shippingMethodIdSelected)[2];
    return Tariff::isTariffEndPointPostamatByCode($tariffCode);
}

function cdek_add_update_form_billing($fragments) {

    $checkout = WC()->checkout();

    parse_str( $_POST['post_data'], $fields_values );

    ob_start();

    echo '<div class="woocommerce-billing-fields__field-wrapper">';

    $fields = $checkout->get_checkout_fields( 'billing' );

    foreach ( $fields as $key => $field ) {
        $value = $checkout->get_value( $key );

        if ( ! $value && ! empty( $fields_values[ $key ] ) ) {
            $value = $fields_values[ $key ];
        }

        woocommerce_form_field( $key, $field, $value );
    }

    echo '</div>';

    $fragments['.woocommerce-billing-fields__field-wrapper'] = ob_get_clean();

    return $fragments;
}

function cdek_override_checkout_fields($fields)
{

    $chosen_methods = WC()->session->get('chosen_shipping_methods');

    if (!$chosen_methods || $chosen_methods[0] === false) {
        return $fields;
    }

    $shippingMethodArray = explode('_', $chosen_methods[0]);
    $shippingMethodName = $shippingMethodArray[0] . '_' . $shippingMethodArray[1];


    if ($shippingMethodName === 'official_cdek') {
        $tariffCode = $shippingMethodArray[2];
        $tariffType = (int)Tariff::getTariffTypeToByCode($tariffCode);

        if (!isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone'] = [
                'label' => 'Телефон',
                'required' => true,
                'class' => ['form-row-wide'],
                'validate' => ['phone'],
                'autocomplete' => 'tel',
                'priority' => 100
            ];
        }

        if (!isset($fields['billing']['billing_city'])) {
            $fields['billing']['billing_city'] = [
                'label' => 'Населённый пункт',
                'required' => true,
                'class' => ['form-row-wide', 'address-field'],
                'autocomplete' => 'address-level2',
                'priority' => 70
            ];
        }

        if (!isset($fields['billing']['billing_state'])) {
            $fields['billing']['billing_state'] = [
                'label' => 'Область / район',
                'required' => true,
                'class' => ['form-row-wide', 'address-field'],
                'validate' => ['state'],
                'autocomplete' => 'address-level1',
                'priority' => 80,
                'country_field' => "billing_country",
                'country' => "RU",
            ];
        }

        if (!$tariffType) {
            if (!isset($fields['billing']['billing_address_1'])) {
                $fields['billing']['billing_address_1'] = [
                    'label' => 'Адрес',
                    'placeholder' => 'Номер дома и название улицы',
                    'required' => true,
                    'class' => ['form-row-wide', 'address-field'],
                    'autocomplete' => 'address-line1',
                    'priority' => 50
                ];
            }
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
    $pvzCode = $_POST['pvz_code'];
    $shippingMethodIdSelected = WC()->session->get('chosen_shipping_methods')[0];
    $tariffCode = getTariffCodeByShippingMethodId($shippingMethodIdSelected);
    if (checkTariffFromStoreByTariffCode($tariffCode)) {
        if (empty($pvzCode)) {
            $pvzCodeTmp = get_post_meta(-1, 'pvz_code_tmp');
            if (empty($pvzCodeTmp[0]['pvz_code'])) {
                wc_add_notice(__('Не выбран пункт выдачи заказа.'), 'error');
            } else {
                $_POST['pvz_code'] = $pvzCodeTmp[0]['pvz_code'];
                $_POST['pvz_info'] = $pvzCodeTmp[0]['pvz_info'];
                $_POST['city_code'] = $pvzCodeTmp[0]['city_code'];
                delete_post_meta(-1, 'pvz_code_tmp');
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
            $order->set_shipping_address_1($pvzInfo);
            $order->save();
        }

        add_post_meta($order_id, 'order_data', [
            'pvz_info' => $pvzInfo,
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
        if (getStateAuth()) {
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

                $cdekShippingSettings = getSettingDataPlugin();
                $hasPackages = false;
                if ($cdekShippingSettings['has_packages'] === 'yes') {
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

function getSettingDataPlugin()
{
    $cdekShipping = WC()->shipping->load_shipping_methods()['official_cdek'];
    return $cdekShipping->settings;
}