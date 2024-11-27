<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Blocks {

    use Automattic\WooCommerce\Utilities\OrderUtil;
    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Helpers\UI;
    use Cdek\Loader;
    use Cdek\Model\Order;
    use Cdek\Traits\CanBeCreated;
    use Throwable;

    class AdminOrderBox
    {
        use CanBeCreated;

        /** @noinspection MissingParameterTypeDeclarationInspection */
        public static function createOrderMetaBox($post, array $meta = []): void
        {
            if ($post instanceof Order) {
                $order = $post;
            } else {
                $order = new Order($post);
            }

            $shipping = $order->getShipping();

            if ($shipping === null) {
                return;
            }

            include Loader::getTemplate('common');

            if ($order->number === null) {
                include Loader::getTemplate(
                    $shipping->getMethod()->has_packages_mode ? 'create_many' : 'create',
                );

                return;
            }

            try {
                $order->loadLegacyStatuses();
            } catch (Throwable $e) {
            }

            include Loader::getTemplate('order');
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

            if ($shipping === null || $shipping->tariff === null) {
                return;
            }

            add_action('admin_enqueue_scripts', [__CLASS__, 'registerOrderScripts']);

            if ((new CdekApi($shipping->getInstanceId()))->authGetError() !== null) {
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
            UI::enqueueScript('cdek-admin-create-order', 'cdek-create-order', true, false, true);
        }

        public function __invoke(): void
        {
            add_action('add_meta_boxes', [__CLASS__, 'registerMetaBoxes'], 100, 2);
        }
    }
}
