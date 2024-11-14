<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\UI {

    use Automattic\WooCommerce\Utilities\OrderUtil;
    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Helpers\UI;
    use Cdek\Loader;
    use Cdek\Model\Order;
    use Cdek\Model\Tariff;
    use Cdek\Traits\CanBeCreated;
    use Exception;
    use Throwable;

    class MetaBoxes
    {
        use CanBeCreated;

        public static function registerMetaBoxes(string $_post_type, $post): void
        {
            if (!$post || !OrderUtil::is_order($post, wc_get_order_types())) {
                return;
            }

            $order = new Order($post);

            $shipping = $order->getShipping();

            if ($shipping === null) {
                return;
            }

            add_action('admin_enqueue_scripts', [__CLASS__, 'registerOrderScripts']);

            $selectedTariff = $shipping->tariff;

            if ($selectedTariff === 0) {
                return;
            }

            if (!(new CdekApi($shipping->getInstanceId()))->checkAuth()) {
                add_meta_box(
                    Config::ORDER_META_BOX_KEY, Loader::getPluginName(), [__CLASS__, 'noAuthMetaBox'],
                    ['woocommerce_page_wc-orders', 'shop_order'],
                    'side',
                    'core',
                );

                return;
            }

            $settings = $shipping->getMethod();

            $address = $settings->get_option('address');

            if ((empty($address) || !isset(json_decode($address, true)['country'])) &&
                Tariff::isFromDoor($selectedTariff)) {
                add_meta_box(
                    Config::ORDER_META_BOX_KEY,
                    Loader::getPluginName(),
                    [__CLASS__, 'noAddressMetaBox'],
                    ['woocommerce_page_wc-orders', 'shop_order'],
                    'side',
                    'core',
                );

                return;
            }

            $office = $settings->get_option('pvz_code');

            if ((empty($office) || !isset(json_decode($office, true)['country'])) &&
                Tariff::isFromOffice($selectedTariff)) {
                add_meta_box(
                    Config::ORDER_META_BOX_KEY,
                    Loader::getPluginName(),
                    [__CLASS__, 'noOfficeMetaBox'],
                    ['woocommerce_page_wc-orders', 'shop_order'],
                    'side',
                    'core',
                );

                return;
            }

            add_meta_box(
                Config::ORDER_META_BOX_KEY,
                Loader::getPluginName(),
                [__CLASS__, 'createOrderMetaBox'],
                ['woocommerce_page_wc-orders', 'shop_order'],
                'side',
                'core',
            );
        }

        public static function noAddressMetaBox(): void
        {
            $settings_page_url = admin_url('admin.php?page=wc-settings&tab=shipping&section='.Config::DELIVERY_NAME);
            $pluginName        = Loader::getPluginName();
            echo '<div>
                <h4>'.esc_html__('Shipping address not specified', 'cdekdelivery').'</h4>
                <p>'.str_replace(
                    '<a>',
                    '<a href="'.esc_url($settings_page_url).'">',
                    sprintf(
                        esc_html__(/* translators: %s: Name of the plugin */
                            'Select the correct sending address in <a>the settings</a> plugin named %s',
                            'cdekdelivery',
                        ),
                        esc_html($pluginName),
                    ),
                ).'</p>
            </div>';
        }

        public static function noOfficeMetaBox(): void
        {
            $settings_page_url = admin_url('admin.php?page=wc-settings&tab=shipping&section='.Config::DELIVERY_NAME);
            $pluginName        = Loader::getPluginName();
            echo '<div>
                <h4>'.esc_html__('Shipping address not specified', 'cdekdelivery').'</h4>
                <p>'.str_replace(
                    '<a>',
                    '<a href="'.esc_url($settings_page_url).'">',
                    sprintf(
                        esc_html__(/* translators: %s: Name of the plugin */
                            'Select the correct sending address in <a>the settings</a> plugin named %s',
                            'cdekdelivery',
                        ),
                        esc_html($pluginName),
                    ),
                ).'</p>
                </div>';
        }

        public static function noAuthMetaBox(): void
        {
            $settings_page_url = admin_url('admin.php?page=wc-settings&tab=shipping&section='.Config::DELIVERY_NAME);
            $pluginName        = Loader::getPluginName();
            echo '<div>
                <h4>'.esc_html__('Authorization failed', 'cdekdelivery').'</h4>
                <p>'.str_replace(
                    '<a>',
                    '<a href="'.esc_url($settings_page_url).'">',
                    sprintf(
                        esc_html__(/* translators: %s: Name of the plugin */
                            'Enter the correct client ID and secret key in <a>the settings</a> plugin named %s',
                            'cdekdelivery',
                        ),
                        esc_html($pluginName),
                    ),
                ).'</p>
                </div>';
        }

        public static function createOrderMetaBox($post): void
        {
            $order     = new Order($post);

            $shipping = $order->getShipping();

            if($shipping === null) {
                return;
            }

            $items = [];
            foreach ($order->items as $item) {
                /** @noinspection OnlyWritesOnParameterInspection */
                $items[$item['product_id']] = ['name' => $item['name'], 'quantity' => $item['quantity']];
            }

            $dateMin = gmdate('Y-m-d');
            $dateMax = gmdate('Y-m-d', strtotime($dateMin." +31 days"));

            $hasPackages = $shipping->getMethod()->has_packages_mode;
            $orderNumber = $order->number ?: null;
            $orderUuid   = $order->uuid ?: null;

            try {
                $cdekStatuses         = $order->loadLegacyStatuses();
                $actionOrderAvailable = $order->isLocked();
            } catch (Exception $e) {
                $cdekStatuses         = [];
                $actionOrderAvailable = true;
            }

            if (!$actionOrderAvailable) {
                self::notAvailableEditOrderData();
            }

            try {
                $fromDoor      = Tariff::isFromDoor($shipping->tariff ?: $order->tariff_id);
                $courierNumber = $order->getIntake()->number;
                $length        = $shipping->length;
                $height        = $shipping->height;
                $width         = $shipping->width;

                include Loader::getPluginPath('templates/admin/create-order.php');
            } catch (Throwable $e) {
            }
        }

        public static function notAvailableEditOrderData(): void
        {
            echo '<div class="notice notice-warning"><p>
                <strong>CDEKDelivery:</strong> '.esc_html__(
                    'Editing the order is not available due to a change in the order status in the CDEK system',
                    'cdekdelivery',
                ).'
            </p></div>';
        }

        public static function registerOrderScripts(): void
        {
            UI::enqueueScript('cdek-admin-create-order', 'cdek-create-order', true);
        }

        public function __invoke(): void
        {
            add_action('add_meta_boxes', [__CLASS__, 'registerMetaBoxes'], 100, 2);
        }
    }
}
