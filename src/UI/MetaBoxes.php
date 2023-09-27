<?php

namespace Cdek\UI;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Cdek\CdekApi;
use Cdek\CdekShippingMethod;
use Cdek\Config;
use Cdek\Helper;
use Cdek\Helpers\CheckoutHelper;
use Cdek\Loader;
use Cdek\Model\CourierMetaData;
use Cdek\Model\OrderMetaData;
use Cdek\Model\Tariff;
use WC_Shipping_Method;
use WP_Post;

class MetaBoxes {
    public static function registerMetaBoxes(): void {
        global $post;

        if (!$post || !OrderUtil::is_order($post->ID, wc_get_order_types())) {
            return;
        }

        $order = wc_get_order($post);

        if (!CheckoutHelper::isCdekShippingMethod($order)) {
            return;
        }

        $cdekTariff = CheckoutHelper::getOrderShippingMethod($order);

        if (!(new CdekApi($cdekTariff->get_data()['instance_id']))->checkAuth()) {
            add_meta_box(Config::ORDER_META_BOX_KEY, Loader::getPluginName(), [__CLASS__, 'noAuthMetaBox'],
                'shop_order', 'side', 'core');

            return;
        }

        $settings = Helper::getActualShippingMethod($cdekTariff->get_data()['instance_id']);

        if (empty($settings->get_option('address')) && Tariff::isTariffFromDoor($cdekTariff->get_meta('tariff_code'))) {
            add_meta_box(Config::ORDER_META_BOX_KEY, Loader::getPluginName(), [__CLASS__, 'noAddressMetaBox'],
                'shop_order', 'side', 'core');

            return;
        }

        if (empty($settings->get_option('pvz_code')) &&
            Tariff::isTariffFromOffice($cdekTariff->get_meta('tariff_code'))) {
            add_meta_box(Config::ORDER_META_BOX_KEY, Loader::getPluginName(), [__CLASS__, 'noOfficeMetaBox'],
                'shop_order', 'side', 'core');

            return;
        }

        add_meta_box(Config::ORDER_META_BOX_KEY, Loader::getPluginName(), [__CLASS__, 'createOrderMetaBox'],
            'shop_order', 'side', 'core');
    }

    public static function noAddressMetaBox(): void {
        $settings_page_url = admin_url('admin.php?page=wc-settings&tab=shipping&section='.Config::DELIVERY_NAME);
        $pluginName        = Loader::getPluginName();
        echo <<<PAGE
        <div>
            <h4>Не задан адрес отправки</h4>
            <p>Выберите корректный адрес отправки в <a href="$settings_page_url">настройках</a> плагина $pluginName</p>
        </div>
        PAGE;
    }

    public static function noOfficeMetaBox(): void {
        $settings_page_url = admin_url('admin.php?page=wc-settings&tab=shipping&section='.Config::DELIVERY_NAME);
        $pluginName        = Loader::getPluginName();
        echo <<<PAGE
        <div>
            <h4>Не задан ПВЗ отправки</h4>
            <p>Выберите корректный ПВЗ для отправки в <a href="$settings_page_url">настройках</a> плагина $pluginName</p>
        </div>
        PAGE;
    }

    public static function noAuthMetaBox(): void {
        $settings_page_url = admin_url('admin.php?page=wc-settings&tab=shipping&section='.Config::DELIVERY_NAME);
        $pluginName        = Loader::getPluginName();
        echo <<<PAGE
        <div>
            <h4>Авторизация не пройдена</h4>
            <p>Введите корректные идентификатор и секретный ключ клиента в <a href="$settings_page_url">настройках</a> плагина $pluginName</p>
        </div>
        PAGE;
    }

    public static function createOrderMetaBox(WP_Post $post, array $meta): void {
        $order = wc_get_order($post);
        $orderData = OrderMetaData::getMetaByOrderId($post->ID);

        $items = [];
        foreach ($order->get_items() as $item) {
            /** @noinspection OnlyWritesOnParameterInspection */
            $items[$item['product_id']] = ['name' => $item['name'], 'quantity' => $item['quantity']];
        }

        $dateMin = date('Y-m-d');
        $dateMax = date('Y-m-d', strtotime($dateMin." +31 days"));

        $shipping = CheckoutHelper::getOrderShippingMethod($order);

        $hasPackages   = Helper::getActualShippingMethod($shipping->get_data()['instance_id'])->get_option('has_packages_mode') === 'yes';
        $orderNumber   = $orderData['order_number'];
        $orderUuid     = $orderData['order_uuid'];
        $orderIdWP     = $post->ID;
        $courierNumber = CourierMetaData::getMetaByOrderId($post->ID)['courier_number'] ?? '';
        $fromDoor      = Tariff::isTariffFromDoor($shipping->get_meta('tariff_code'));

        include __DIR__.'/../../templates/admin/create-order.php';
    }

    public function __invoke() {
        add_action('add_meta_boxes', [__CLASS__, 'registerMetaBoxes']);
    }
}
