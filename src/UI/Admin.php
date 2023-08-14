<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\UI {

    use Cdek\Loader;

    class Admin {
        public static function registerScripts(): void {
            wp_enqueue_script('cdek-admin-delivery', Loader::getPluginUrl().'assets/js/delivery.js', ['jquery'],
                Loader::getPluginVersion(), true);
            wp_localize_script('cdek-admin-delivery', 'cdek_rest_delivery_api_path', [
                'get_pvz'             => rest_url('/cdek/v1/get-pvz'),
                'create_order'        => rest_url('/cdek/v1/create-order'),
                'delete_order'        => rest_url('/cdek/v1/delete-order'),
                'get_region'          => rest_url('/cdek/v1/get-region'),
                'call_courier'        => rest_url('/cdek/v1/call-courier'),
                'call_courier_delete' => rest_url('/cdek/v1/call-courier-delete'),
            ]);

            wp_enqueue_script('cdek-admin-create-order', Loader::getPluginUrl().'assets/js/create-order.js',
                ['jquery'], Loader::getPluginVersion(), true);
            wp_localize_script('cdek-admin-create-order', 'cdek_rest_order_api_path', [
                'create_order' => rest_url('/cdek/v1/create-order'),
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
