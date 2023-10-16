<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\UI {

    use Cdek\Helper;
    use Cdek\Loader;

    class CdekWidget {
        public static function registerScripts(): void {
            wp_register_script('cdek-widget',
                isset($_ENV['CDEK_LOCAL_WIDGET']) ? Loader::getPluginUrl().'assets/js/cdek-widget.umd.js' :
                    '//cdn.jsdelivr.net/gh/cdek-it/widget@3.4/dist/cdek-widget.umd.js');
            wp_localize_script('cdek-widget', 'cdek', [
                'apiKey' => Helper::getActualShippingMethod()->get_option('yandex_map_api_key'),
            ]);
        }

        public function __invoke() {
            add_action('wp_enqueue_scripts', [__CLASS__, 'registerScripts']);
            add_action('admin_enqueue_scripts', [__CLASS__, 'registerScripts']);
        }
    }
}
