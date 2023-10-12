<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\UI {

    use Cdek\Config;
    use Cdek\Helpers\UrlHelper;
    use Cdek\Loader;

    class Admin {
        public static function registerAdminScripts(): void {
            if (!isset($_GET['section']) || $_GET['section'] !== Config::DELIVERY_NAME) {
                return;
            }

            wp_enqueue_script('cdek-admin-settings', Loader::getPluginUrl().'assets/js/admin-settings.js',
                ['jquery', 'cdek-widget'], Loader::getPluginVersion(), true);
            wp_localize_script('cdek-admin-settings', 'cdek_admin_settings', [
                'api'   => [
                    'offices' => UrlHelper::buildRest('/get-offices'),
                ],
                'icons' => [
                    'door'   => Loader::getPluginUrl().'assets/img/door-enter.svg',
                    'office' => Loader::getPluginUrl().'assets/img/building-skyscraper.svg',
                ],
            ]);
        }

        public static function registerAdminStyles(): void {
            wp_enqueue_style('cdek-admin-settings', Loader::getPluginUrl().'assets/css/admin-settings.css', [],
                Loader::getPluginVersion());
        }

        public static function registerOrderScripts(): void {
            wp_enqueue_script('cdek-admin-create-order', Loader::getPluginUrl().'assets/js/create-order.js', ['jquery'],
                Loader::getPluginVersion(), true);
            wp_localize_script('cdek-admin-create-order', 'cdek_rest_order_api_path', [
                'create_order'        => UrlHelper::buildRest('/create-order'),
                'delete_order'        => UrlHelper::buildRest('/delete-order'),
                'get_region'          => UrlHelper::buildRest('/get-region'),
                'call_courier'        => UrlHelper::buildRest('/call-courier'),
                'call_courier_delete' => UrlHelper::buildRest('/call-courier-delete'),
            ]);
        }

        public function __invoke(): void {
            add_action('load-woocommerce_page_wc-settings', [__CLASS__, 'registerAdminScripts']);
            add_action('admin_enqueue_scripts', [__CLASS__, 'registerAdminStyles']);

            add_action('admin_enqueue_scripts', [__CLASS__, 'registerOrderScripts']);
        }
    }

}
