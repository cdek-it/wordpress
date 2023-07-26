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

use Cdek\CallCourier;
use Cdek\CdekApi;
use Cdek\CreateOrder;
use Cdek\DataWPScraber;
use Cdek\DeleteOrder;
use Cdek\Helper;
use Cdek\Model\CourierMetaData;
use Cdek\Model\OrderMetaData;
use Cdek\Model\Tariff;
use Cdek\WeightCalc;

if (!function_exists('add_action')) {
    exit();
}

require 'vendor/autoload.php';
require_once(plugin_dir_path(__FILE__) . 'message.php');
require_once(plugin_dir_path(__FILE__) . 'config.php');

add_action('rest_api_init', 'cdek_register_route');
add_filter('woocommerce_new_order', 'cdek_woocommerce_new_order_action', 10, 2);
add_filter('woocommerce_shipping_methods', 'add_cdek_shipping_method');
add_action('woocommerce_shipping_init', 'cdek_shipping_method');
add_action('woocommerce_after_shipping_rate', 'cdek_map_display', 10, 2);
add_action('woocommerce_checkout_process', 'is_pvz_code');
add_action('wp_enqueue_scripts', 'cdek_widget_enqueue_script');
add_action('admin_enqueue_scripts', 'cdek_admin_enqueue_script');
add_filter('woocommerce_update_order_review_fragments', 'cdek_add_update_form_billing', 99);
add_action('wp_footer', 'cdek_add_script_update_shipping_method');
add_filter('woocommerce_checkout_fields', 'cdek_add_custom_checkout_field');
add_action('woocommerce_checkout_create_order', 'cdek_save_custom_checkout_field_to_order', 10, 2);


function cdek_widget_enqueue_script()
{
    if (is_checkout()) {
        wp_enqueue_script('cdek-map', plugin_dir_url(__FILE__) . 'assets/js/map-v10.js', array('jquery'), '1.7.0', true);
        wp_localize_script('cdek-map', 'cdek_rest_api_path', array(
            'rest_path' => get_rest_path(),
        ));

        wp_enqueue_script('cdek-css-leaflet-min', plugin_dir_url(__FILE__) . 'assets/js/lib/leaflet-src.min.js');
        wp_enqueue_script('cdek-admin-leaflet-cluster', plugin_dir_url(__FILE__) . 'assets/js/lib/leaflet.markercluster-src.min.js');
        wp_enqueue_style('cdek-css-leaflet', plugin_dir_url(__FILE__) . 'assets/css/leaflet.css');
        wp_enqueue_style('cdek-admin-leaflet-cluster-default', plugin_dir_url(__FILE__) . 'assets/css/MarkerCluster.Default.min.css');
        wp_enqueue_style('cdek-admin-leaflet-cluster', plugin_dir_url(__FILE__) . 'assets/css/MarkerCluster.min.css');
        wp_enqueue_style('cdek-css', plugin_dir_url(__FILE__) . 'assets/css/cdek-map-v5.css');
        addYandexMap();
    }
}

function cdek_admin_enqueue_script()
{
    wp_enqueue_script('cdek-admin-delivery', plugin_dir_url(__FILE__) . 'assets/js/delivery-v8.js', array('jquery'), '1.7.0', true);
    wp_localize_script('cdek-admin-delivery', 'cdek_rest_api_path', array(
        'rest_path' => get_rest_path(),
    ));

    wp_enqueue_script('cdek-admin-create-order', plugin_dir_url(__FILE__) . 'assets/js/create-order-v3.js', array('jquery'), '1.7.0', true);
    wp_localize_script('cdek-admin-create-order', 'cdek_rest_api_path', array(
        'rest_path' => get_rest_path(),
    ));

    wp_enqueue_script('cdek-admin-leaflet', plugin_dir_url(__FILE__) . 'assets/js/lib/leaflet-src.min.js');
    wp_enqueue_script('cdek-admin-leaflet-cluster', plugin_dir_url(__FILE__) . 'assets/js/lib/leaflet.markercluster-src.min.js');
    wp_enqueue_style('cdek-admin-leaflet', plugin_dir_url(__FILE__) . 'assets/css/leaflet.css');
    wp_enqueue_style('cdek-admin-leaflet-cluster-default', plugin_dir_url(__FILE__) . 'assets/css/MarkerCluster.Default.min.css');
    wp_enqueue_style('cdek-admin-leaflet-cluster', plugin_dir_url(__FILE__) . 'assets/css/MarkerCluster.min.css');
    wp_enqueue_style('cdek-admin-delivery', plugin_dir_url(__FILE__) . 'assets/css/delivery-v4.css');
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

function get_rest_path() {
    $rest_url = get_rest_url(); // Get the base REST URL for the site
    $rest_url_parts = parse_url($rest_url); // Parse the URL into its components
    return $rest_url_parts['path'];
}

function cdek_register_route()
{
    register_rest_route('cdek/v1', '/check-auth', array(
        'methods' => 'GET',
        'callback' => 'check_auth',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('cdek/v1', '/get-region', array(
        'methods' => 'GET',
        'callback' => 'get_region',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('cdek/v1', '/get-city-code', array(
        'methods' => 'GET',
        'callback' => 'get_city_code',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('cdek/v1', '/get-pvz', array(
        'methods' => 'GET',
        'callback' => 'get_pvz',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('cdek/v1', '/create-order', array(
        'methods' => 'POST',
        'callback' => 'create_order',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('cdek/v1', '/create-order', array(
        'methods' => 'GET',
        'callback' => 'create_order',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('cdek/v1', '/delete-order', array(
        'methods' => 'GET',
        'callback' => 'delete_order',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('cdek/v1', '/get-waybill', array(
        'methods' => 'GET',
        'callback' => 'get_waybill',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('cdek/v1', '/set-pvz-code-tmp', array(
        'methods' => 'GET',
        'callback' => 'set_pvz_code_tmp',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('cdek/v1', '/call-courier', array(
        'methods' => 'POST',
        'callback' => 'call_courier',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('cdek/v1', '/call-courier-delete', array(
        'methods' => 'GET',
        'callback' => 'call_courier_delete',
        'permission_callback' => '__return_true',
    ));

}

function call_courier($data)
{
    $callCourier = new CallCourier();
    $param = DataWPScraber::getData($data, [
            'order_id',
            'date',
            'starttime',
            'endtime',
            'desc',
            'name',
            'phone',
            'address',
            'comment',
            'weight',
            'length',
            'width',
            'height',
            'need_call',
    ]);
    return $callCourier->call($param);
}

function call_courier_delete($data)
{
    $callCourier = new CallCourier();
    return $callCourier->delete($data->get_param('order_id'));
}

function create_order($data)
{
    $createOrder = new CreateOrder();
    return $createOrder->createOrder($data);
}

function getCityCode($city_code, $order)
{
    $api = new CdekApi();
    $cityCode = $city_code;
    if (empty($cityCode)) {
        $cityCode = $api->getCityCodeByCityName($order->get_shipping_city(), $order->get_shipping_state());
    }
    return $cityCode;
}

function setPackage($data, $orderId, $currency)
{
    $param = [];
    $cdekShippingSettings = Helper::getSettingDataPlugin();
    if ($cdekShippingSettings['has_packages_mode'] === 'yes') {
        $packageData = json_decode($data->get_param('package_data'));
        $param['packages'] = get_packages($orderId, $packageData, $currency);
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

            if ($currency !== 'RUB' && function_exists('wcml_get_woocommerce_currency_option')) {
                $cost = convert_currency_cost_to_rub($cost, $currency);
            }

            $selectedPaymentMethodId = $order->get_payment_method();
            $percentCod = (int)$cdekShippingSettings['percentcod'];
            if ($selectedPaymentMethodId === 'cod') {
                if ($percentCod !== 0) {
                    $paymentValue = (int)(((int)$cdekShippingSettings['percentcod'] / 100) * $cost);
                } else {
                    $paymentValue = $cost;
                }
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

function convert_currency_cost_to_rub($cost, $currency)
{
    global $woocommerce_wpml;

    $multiCurrency = $woocommerce_wpml->get_multi_currency();
    $rates = $multiCurrency->get_exchange_rates();

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
        $cost = round($cost * (float)$rates['RUB'], 2);
    } else {
        $costConvertToDefault = round($cost / (float)$rates[$currency], 2);
        $cost = round($costConvertToDefault * (float)$rates['RUB'], 2);
    }

    return $cost;
}

function get_packages($orderId, $packageData, $currency)
{
    $result = [];
    foreach ($packageData as $key => $package) {
        $data = get_package_items($package->items, $orderId, $currency);
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

function get_package_items($items, $orderId, $currency)
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

        $cost = $product->get_price();

        if ($currency !== 'RUB' && function_exists('wcml_get_woocommerce_currency_option')) {
            $cost = convert_currency_cost_to_rub($cost, $currency);
        }

        $itemsData[] = [
            "ware_key" => $product->get_id(),
            "payment" => ["value" => $paymentValue],
            "name" => $product->get_name(),
            "cost" => $cost,
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

    $order = json_decode($api->getOrder($data->get_param('number')));

    if ($waybill->requests[0]->state === 'INVALID' || property_exists($waybill->requests[0], 'errors')
        || !property_exists($order, 'related_entities')) {
        echo '
        Не удалось создать квитанцию. 
        Для решения проблемы, попробуй пересоздать заказ. Нажмите кнопку "Отменить"
        и введите габариты упаковки повторно.';
        exit();
    }

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
    return $api->getPvz($data->get_param('city_code'), $data->get_param('weight'), $data->get_param('admin'));
}

function delete_order($data)
{
    $deleteOrder = new DeleteOrder();
    return $deleteOrder->delete($data->get_param('order_id'), $data->get_param('number'));
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

        $meta = $shippingMethodCurrent->get_meta_data();
        $weight = $meta['total_weight_kg'];

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
    $shipToDestination = get_option('woocommerce_ship_to_destination');
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
                if (array_key_exists('billing_address_1', $_POST)) {
                    if (empty($_POST['billing_address_1'])) {
                        wc_add_notice(__('Нет адреса отправки.'), 'error');
                    }
                }
            } else {
                if (array_key_exists('shipping_address_1', $_POST)) {
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
        $pvzInfo = array_key_exists('pvz_info', $_POST) ? $_POST['pvz_info'] : null;
        $pvzCode = array_key_exists('pvz_code', $_POST) ? $_POST['pvz_code'] : null;
        $tariffId = getTariffCodeCdekShippingMethodByOrder($order);
        $cityCode = array_key_exists('city_code', $_POST) ? $_POST['city_code'] : null;

        $currency = 'RUB';
        if (function_exists('wcml_get_woocommerce_currency_option')) {
            $currency = get_woocommerce_currency();
        }

        $api = new CdekApi();
        if (empty($cityCode)) {
            $pvzInfo = $order->get_billing_address_1();
            $cityCode = $api->getCityCodeByCityName($order->get_billing_city(), $order->get_billing_city());
        }
        if (empty($pvzInfo) && Tariff::isTariffToStoreByCode($tariffId)) {
            $pvzInfo = $order->get_billing_address_1();
            $code = $api->getPvzCodeByPvzAddressNCityCode($pvzInfo, $cityCode);
            if ($code !== false) {
                $pvzCode = $code;
            }
        }
        $cityData = $api->getCityByCode($cityCode);
        $order->set_shipping_address_1($pvzInfo);
        $order->set_shipping_city($cityData['city']);
        $order->set_shipping_state($cityData['region']);
        $order->save();

        if (Tariff::isTariffToStoreByCode($tariffId)) {
            $shippingMethodArray = $order->get_items('shipping');
            $shippingMethod = array_shift($shippingMethodArray);
            $shippingMethod->add_meta_data('pvz', $pvzCode . ' (' . $pvzInfo . ')');
            $shippingMethod->save_meta_data();
        }

        $data = [
            'pvz_address' => $pvzInfo,
            'pvz_code' => $pvzCode,
            'tariff_id' => $tariffId,
            'city_code' => $cityCode,
            'currency' => $currency,
            'order_number' => '',
            'order_uuid' => '',
        ];

        OrderMetaData::addMetaByOrderId($order_id, $data);
    }

}

function add_cdek_shipping_method($methods)
{
    $methods['official_cdek'] = 'CdekShippingMethod';
    return $methods;
}

function add_custom_order_meta_box()
{
    /**
     * 1. Получить айди заказа
     * 2. Проверить создан ли заказ с нашим тарифом
     * 3. Проверить есть ли доступ
     * 4. Проверить существует ли заказ, если нет очистить метаданные
     * 5. Проверить существует ли заявка, если нет очистить метаданные
     * 6. Заполнить поля нужные для работы виджета, передать их в виджет
     */
    global $post;
    if ($post && $post->post_type === 'shop_order') {
        $order_id = $post->ID;
        $order = wc_get_order($order_id);
        if (isCdekShippingMethod($order)) {
            $api = new CdekApi();
            if ($api->checkAuth()) {
                $createOrder = new CreateOrder();
                $createOrder->deleteIfNotExist($order_id);

                $callCourier = new CallCourier();
                $callCourier->deleteIfNotExist($order_id);
                CourierMetaData::getMetaByOrderId($order_id);
                //Сбор данных
                $orderWP = $order->get_id();
                $postOrderData = OrderMetaData::getMetaByOrderId($orderWP);
                $orderNumber = getOrderNumber($postOrderData);
                $orderUuid = getOrderUuid($postOrderData);
                $items = getItems($order);
                $dateMin = date('Y-m-d');
                $dateMax = getDateMax($dateMin);
                $courierNumber = getCourierNumber($order_id);

                add_meta_box(
                    'cdek_create_order_box',
                    'CDEKDelivery',
                    'render_cdek_create_order_box',
                    'shop_order',
                    'side',
                    'core',
                    [
                        'status' => true,
                        'hasPackages' => isHasPackages(),
                        'orderNumber' => $orderNumber,
                        'orderIdWP' => $orderWP,
                        'orderUuid' => $orderUuid,
                        'dateMin' => $dateMin,
                        'dateMax' => $dateMax,
                        'items' => $items,
                        'courierNumber' => $courierNumber,
                        'fromDoor' => Tariff::isTariffFromDoorByCode($postOrderData['tariff_id'])
                    ]
                );
            } else {
                add_meta_box(
                    'cdek_create_order_box',
                    'CDEKDelivery',
                    'render_cdek_create_order_box',
                    'shop_order',
                    'side',
                    'core',
                    [
                        'status' => false,
                    ]
                );

            }
        }
    }


}

/**
 * @param $order
 * @return array
 */
function getItems($order): array
{
    $items = [];
    foreach ($order->get_items() as $item) {
        $items[$item['product_id']] = ['name' => $item['name'], 'quantity' => $item['quantity']];
    }
    return $items;
}

/**
 * @param $postOrderData
 * @return mixed
 */
function getOrderUuid($postOrderData)
{
    if (array_key_exists('cdek_order_waybill', $postOrderData)) {
        $orderUuid = $postOrderData['cdek_order_waybill'];
    } else {
        $orderUuid = $postOrderData['order_uuid'];
    }
    return $orderUuid;
}

/**
 * @param $postOrderData
 * @return mixed
 */
function getOrderNumber($postOrderData)
{
    if (array_key_exists('cdek_order_uuid', $postOrderData)) {
        $orderNumber = $postOrderData['cdek_order_uuid'];
    } else {
        $orderNumber = $postOrderData['order_number'];
    }
    return $orderNumber;
}

/**
 * @param int $order_id
 * @return mixed|string
 */
function getCourierNumber(int $order_id)
{
    $courierMeta = CourierMetaData::getMetaByOrderId($order_id);
    $courierNumber = '';
    if (!empty($courierMeta)) {
        $courierNumber = $courierMeta['courier_number'];
    }
    return $courierNumber;
}

/**
 * @param $dateMin
 * @return false|string
 */
function getDateMax($dateMin)
{
    $dateMaxUnix = strtotime($dateMin . " +31 days");
    return date('Y-m-d', $dateMaxUnix);
}

/**
 * @return bool
 */
function isHasPackages(): bool
{
    $cdekShippingSettings = Helper::getSettingDataPlugin();
    $hasPackages = false;
    if ($cdekShippingSettings['has_packages_mode'] === 'yes') {
        $hasPackages = true;
    }
    return $hasPackages;
}

add_action('add_meta_boxes', 'add_custom_order_meta_box');

function render_cdek_create_order_box($post, $metabox)
{
    $args = $metabox['args'];
    if ($args['status']) {
        $hasPackages = $args['hasPackages'];
        $orderNumber = $args['orderNumber'];
        $orderIdWP = $args['orderIdWP'];
        $orderUuid = $args['orderUuid'];
        $dateMin = $args['dateMin'];
        $dateMax = $args['dateMax'];
        $items = $args['items'];
        $courierNumber = $args['courierNumber'];
        $fromDoor = $args['fromDoor'];
        ob_start();
        include 'templates/admin/create-order.php';
        $content = ob_get_clean();
        echo $content;
    } else {
        $settings_page_url = admin_url('admin.php?page=wc-settings&tab=shipping&section=official_cdek');
        echo '<div class="cdek_create_order_box">';
        echo '<h4>Авторизация не пройдена</h4>';
        echo '<p>Введите корректные идентификатор и секретный ключ клиента в <a href="' . $settings_page_url . '">настройках</a> плагина CDEKDelivery</p>';
        echo '</div>';
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

function cdek_add_custom_checkout_field($fields)
{
    $cdekShippingSettings = Helper::getSettingDataPlugin();
    if ($cdekShippingSettings['international_mode'] === 'yes') {
        $fields['billing']['passport_series'] = [
            'label' => __('Серия паспорта', 'woocommerce'),
            'required' => true,
            'class' => ['form-row-wide'],
            'clear' => true,
            'custom_attributes' => [
                'maxlength' => 4,
            ],
        ];
        $fields['billing']['passport_number'] = [
            'label' => __('Номер паспорта', 'woocommerce'),
            'required' => true,
            'class' => ['form-row-wide'],
            'clear' => true,
            'custom_attributes' => [
                'maxlength' => 6,
            ],
        ];
        $fields['billing']['passport_date_of_issue'] = [
            'type' => 'date',
            'label' => __('Дата выдачи паспорта', 'woocommerce'),
            'required' => true,
            'class' => ['form-row-wide'],
            'clear' => true
        ];
        $fields['billing']['passport_organization'] = [
            'label' => __('Орган выдачи паспорта', 'woocommerce'),
            'required' => true,
            'class' => ['form-row-wide'],
            'clear' => true,
        ];
        $fields['billing']['tin'] = [
            'label' => __('ИНН', 'woocommerce'),
            'required' => true,
            'class' => ['form-row-wide'],
            'clear' => true,
            'custom_attributes' => [
                'maxlength' => 12,
            ],
        ];
        $fields['billing']['passport_date_of_birth'] = [
            'type' => 'date',
            'label' => __('Дата рождения', 'woocommerce'),
            'required' => true,
            'class' => ['form-row-wide'],
            'clear' => true
        ];
    }

    return $fields;
}

function cdek_save_custom_checkout_field_to_order($order, $data)
{
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
