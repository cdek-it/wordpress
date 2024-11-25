<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\UI {

    use Cdek\Loader;
    use Cdek\ShippingMethod;
    use Cdek\Traits\CanBeCreated;

    class CdekWidget
    {
        use CanBeCreated;

        public static function registerScripts(): void
        {
            wp_register_script('cdek-widget', Loader::getPluginUrl('build/cdek-widget.umd.js'));

            $shipping = ShippingMethod::factory();

            wp_localize_script('cdek-widget', 'cdek', [
                'key' => $shipping->yandex_map_api_key,
                'close' => $shipping->map_auto_close,
                'lang' => mb_strpos(get_user_locale(), 'en') === 0 ? 'eng' : 'rus',
            ]);
        }

        public function __invoke(): void
        {
            add_action('wp_enqueue_scripts', [__CLASS__, 'registerScripts']);
        }
    }
}
