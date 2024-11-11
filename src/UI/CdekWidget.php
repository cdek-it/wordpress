<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\UI {

    use Cdek\Helper;
    use Cdek\Loader;
    use Cdek\Traits\CanBeCreated;

    class CdekWidget
    {
        use CanBeCreated;

        public static function registerScripts(): void
        {
            wp_register_script('cdek-widget', Loader::getPluginUrl('build/cdek-widget.umd.js'));

            wp_localize_script('cdek-widget', 'cdek', [
                'apiKey' => Helper::getActualShippingMethod()->get_option('yandex_map_api_key'),
            ]);
        }

        public function __invoke(): void
        {
            add_action('wp_enqueue_scripts', [__CLASS__, 'registerScripts']);
            add_action('admin_enqueue_scripts', [__CLASS__, 'registerScripts']);
        }
    }
}
