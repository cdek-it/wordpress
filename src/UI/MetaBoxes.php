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
    use Cdek\Traits\CanBeCreated;
    use Throwable;

    class MetaBoxes
    {
        use CanBeCreated;

        /** @noinspection MissingParameterTypeDeclarationInspection */
        public static function createOrderMetaBox($post): void
        {
            $order = new Order($post);

            $shipping = $order->getShipping();

            if ($shipping === null) {
                return;
            }

            $items = [];
            foreach ($order->items as $item) {
                /** @noinspection OnlyWritesOnParameterInspection */
                $items[$item['product_id']] = ['name' => $item['name'], 'quantity' => $item['quantity']];
            }

            if ($order->number !== null) {
                try {
                    $order->loadLegacyStatuses();
                } catch (Throwable $e) {
                }

                if ($order->isLocked() !== false) {
                    echo '<div class="notice notice-warning"><p>
                <strong>'.Loader::getPluginName().':</strong> '.esc_html__(
                            'Editing the order is not available due to a change in the order status in the CDEK system',
                            'cdekdelivery',
                        ).'
            </p></div>';
                }
            }

            try {
                include Loader::getTemplate(
                    $shipping->getMethod()->has_packages_mode ? 'form_package_many' : 'form_package',
                );

                include Loader::getTemplate('order_created');
            } catch (Throwable $e) {
            }
        }

        public static function noAuthMetaBox(): void
        {
            echo '<div>
                <h4>'.esc_html__('Authorization failed', 'cdekdelivery').'</h4>
                <p>'.str_replace(
                    '<a>',
                    '<a href="'.
                    esc_url(admin_url('admin.php?page=wc-settings&tab=shipping&section='.Config::DELIVERY_NAME)).
                    '">',
                    sprintf(
                        esc_html__(/* translators: %s: Name of the plugin */
                            'Enter the correct client ID and secret key in <a>the settings</a> plugin named %s',
                            'cdekdelivery',
                        ),
                        esc_html(Loader::getPluginName()),
                    ),
                ).'</p>
                </div>';
        }

        /** @noinspection MissingParameterTypeDeclarationInspection */

        public static function registerMetaBoxes(string $_post_type, $post): void
        {
            if (empty($post) || !OrderUtil::is_order($post, wc_get_order_types())) {
                return;
            }

            $order = new Order($post);

            $shipping = $order->getShipping();

            if ($shipping === null) {
                return;
            }

            add_action('admin_enqueue_scripts', [__CLASS__, 'registerOrderScripts']);

            if ($shipping->tariff === 0) {
                return;
            }

            if (!(new CdekApi($shipping->getInstanceId()))->checkAuth()) {
                add_meta_box(
                    Config::ORDER_META_BOX_KEY,
                    Loader::getPluginName(),
                    [__CLASS__, 'noAuthMetaBox'],
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
