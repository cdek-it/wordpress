<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\UI {

    use Automattic\WooCommerce\Utilities\OrderUtil;
    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Helper;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\Loader;
    use Cdek\MetaKeys;
    use Cdek\Model\CourierMetaData;
    use Cdek\Model\OrderMetaData;
    use Cdek\Model\Tariff;
    use Exception;
    use Throwable;

    class MetaBoxes
    {
        public static function registerMetaBoxes(string $post_type, $post): void
        {
            if (!$post || !OrderUtil::is_order($post, wc_get_order_types())) {
                return;
            }

            $order = wc_get_order($post);

            if (!CheckoutHelper::isCdekShippingMethod($order)) {
                return;
            }

            add_action('admin_enqueue_scripts', [__CLASS__, 'registerOrderScripts']);

            $cdekMethod     = CheckoutHelper::getOrderShippingMethod($order);
            $selectedTariff = (int) ($cdekMethod->get_meta(MetaKeys::TARIFF_CODE) ?:
                $cdekMethod->get_meta('tariff_code'));

            if ($selectedTariff === 0) {
                return;
            }

            if (!(new CdekApi($cdekMethod->get_data()['instance_id']))->checkAuth()) {
                add_meta_box(Config::ORDER_META_BOX_KEY, Loader::getPluginName(), [__CLASS__, 'noAuthMetaBox'],
                             ['woocommerce_page_wc-orders', 'shop_order'], 'side', 'core');

                return;
            }

            $settings = Helper::getActualShippingMethod($cdekMethod->get_data()['instance_id']);

            $address = $settings->get_option('address');

            if ((empty($address) || !isset(json_decode($address, true)['country'])) &&
                Tariff::isTariffFromDoor($selectedTariff)) {
                add_meta_box(Config::ORDER_META_BOX_KEY, Loader::getPluginName(), [__CLASS__, 'noAddressMetaBox'],
                             ['woocommerce_page_wc-orders', 'shop_order'], 'side', 'core');

                return;
            }

            $office = $settings->get_option('pvz_code');

            if ((empty($office) || !isset(json_decode($office, true)['country'])) &&
                Tariff::isTariffFromOffice($selectedTariff)) {
                add_meta_box(Config::ORDER_META_BOX_KEY, Loader::getPluginName(), [__CLASS__, 'noOfficeMetaBox'],
                             ['woocommerce_page_wc-orders', 'shop_order'], 'side', 'core');

                return;
            }

            add_meta_box(Config::ORDER_META_BOX_KEY, Loader::getPluginName(), [__CLASS__, 'createOrderMetaBox'],
                         ['woocommerce_page_wc-orders', 'shop_order'], 'side', 'core');
        }

        public static function noAddressMetaBox(): void
        {
            $settings_page_url = admin_url('admin.php?page=wc-settings&tab=shipping&section='.Config::DELIVERY_NAME);
            $pluginName        = Loader::getPluginName();
            echo '<div>
                <h4>'.
                 esc_html__('Shipping address not specified', 'cdekdelivery').
                 '</h4>
                <p>'.
                 str_replace('<a>', '<a href="'.esc_url($settings_page_url).'">',
                             sprintf(esc_html__(/* translators: %s: Name of the plugin */ 'Select the correct sending address in <a>the settings</a> plugin named %s',
                                                                                          'cdekdelivery'),
                                     esc_html($pluginName))).
                 '</p>
            </div>';
        }

        public static function noOfficeMetaBox(): void
        {
            $settings_page_url = admin_url('admin.php?page=wc-settings&tab=shipping&section='.Config::DELIVERY_NAME);
            $pluginName        = Loader::getPluginName();
            echo '<div>
                <h4>'.
                 esc_html__('Shipping address not specified', 'cdekdelivery').
                 '</h4>
                <p>'.
                 str_replace('<a>', '<a href="'.esc_url($settings_page_url).'">',
                             sprintf(esc_html__(/* translators: %s: Name of the plugin */ 'Select the correct sending address in <a>the settings</a> plugin named %s',
                                                                                          'cdekdelivery'),
                                     esc_html($pluginName))).
                 '</p>
                </div>';
        }

        public static function noAuthMetaBox(): void
        {
            $settings_page_url = admin_url('admin.php?page=wc-settings&tab=shipping&section='.Config::DELIVERY_NAME);
            $pluginName        = Loader::getPluginName();
            echo '<div>
                <h4>'.
                 esc_html__('Authorization failed', 'cdekdelivery').
                 '</h4>
                <p>'.
                 str_replace('<a>', '<a href="'.esc_url($settings_page_url).'">',
                             sprintf(esc_html__(/* translators: %s: Name of the plugin */ 'Enter the correct client ID and secret key in <a>the settings</a> plugin named %s',
                                                                                          'cdekdelivery'),
                                     esc_html($pluginName))).
                 '</p>
                </div>';
        }

        public static function createOrderMetaBox($post): void
        {
            $order     = wc_get_order($post);
            $orderIdWP = $order->get_id();
            $orderData = OrderMetaData::getMetaByOrderId($orderIdWP);

            $items = [];
            foreach ($order->get_items() as $item) {
                /** @noinspection OnlyWritesOnParameterInspection */
                $items[$item['product_id']] = ['name' => $item['name'], 'quantity' => $item['quantity']];
            }

            $dateMin = gmdate('Y-m-d');
            $dateMax = gmdate('Y-m-d', strtotime($dateMin." +31 days"));

            $shipping = CheckoutHelper::getOrderShippingMethod($order);

            $hasPackages
                = Helper::getActualShippingMethod($shipping->get_data()['instance_id'])
                        ->get_option('has_packages_mode') === 'yes';
            $orderNumber = $orderData['order_number'] ?? null;
            $orderUuid   = $orderData['order_uuid'] ?? null;

            try {
                $cdekStatuses         = Helper::getCdekOrderStatuses($orderUuid);
                $actionOrderAvailable = Helper::getCdekActionOrderAvailable($cdekStatuses);
            } catch (Exception $e) {
                $cdekStatuses         = [];
                $actionOrderAvailable = true;
            }

            if (!$actionOrderAvailable) {
                self::notAvailableEditOrderData();
            }

            try {
                $fromDoor      = Tariff::isTariffFromDoor($shipping->get_meta(MetaKeys::TARIFF_CODE) ?:
                                                              $shipping->get_meta('tariff_code') ?:
                                                                  $orderData['tariff_id']);
                $courierNumber = CourierMetaData::getMetaByOrderId($orderIdWP)['courier_number'] ?? '';
                $length        = $shipping->get_meta(MetaKeys::LENGTH) ?: $shipping->get_meta('length');
                $height        = $shipping->get_meta(MetaKeys::HEIGHT) ?: $shipping->get_meta('height');
                $width         = $shipping->get_meta(MetaKeys::WIDTH) ?: $shipping->get_meta('width');

                include __DIR__.'/../../templates/admin/create-order.php';
            } catch (Throwable $e) {
            }
        }

        public static function notAvailableEditOrderData(): void
        {
            echo '<div class="notice notice-warning"><p>
                <strong>CDEKDelivery:</strong> '.
                 esc_html__('Editing the order is not available due to a change in the order status in the CDEK system',
                            'cdekdelivery').
                 '
            </p></div>';
        }

        public static function registerOrderScripts(): void
        {
            Helper::enqueueScript('cdek-admin-create-order', 'cdek-create-order', true);
        }

        public function __invoke(): void
        {
            add_action('add_meta_boxes', [__CLASS__, 'registerMetaBoxes'], 100, 2);
        }
    }
}
