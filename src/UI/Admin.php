<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\UI {

    use Cdek\Helpers\UrlHelper;
    use Cdek\Loader;

    class Admin {
        public static function registerScripts(): void {
            wp_enqueue_script('cdek-admin-delivery', Loader::getPluginUrl().'assets/js/delivery.js', ['jquery'],
                Loader::getPluginVersion(), true);
            wp_localize_script('cdek-admin-delivery', 'cdek_rest_delivery_api_path', [
                'get_pvz'             => UrlHelper::buildRest('/get-pvz'),
                'create_order'        => UrlHelper::buildRest('/create-order'),
                'delete_order'        => UrlHelper::buildRest('/delete-order'),
                'get_region'          => UrlHelper::buildRest('/get-region'),
                'call_courier'        => UrlHelper::buildRest('/call-courier'),
                'call_courier_delete' => UrlHelper::buildRest('/call-courier-delete'),
            ]);

            wp_enqueue_script('cdek-admin-create-order', Loader::getPluginUrl().'assets/js/create-order.js', ['jquery'],
                Loader::getPluginVersion(), true);
            wp_localize_script('cdek-admin-create-order', 'cdek_rest_order_api_path', [
                'create_order' => UrlHelper::buildRest('/create-order'),
            ]);
        }

        public static function registerStyles(): void {
            wp_enqueue_style('cdek-admin-delivery', Loader::getPluginUrl().'assets/css/delivery.css', [],
                Loader::getPluginVersion());
        }

        public function __invoke(): void {
            add_action('admin_enqueue_scripts', [__CLASS__, 'registerScripts']);
            add_action('admin_enqueue_scripts', [__CLASS__, 'registerStyles']);

            add_action('admin_enqueue_scripts', new YandexMap);
        }
    }

}
