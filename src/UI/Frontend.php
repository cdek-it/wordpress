<?php

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\UI {

    use Cdek\Helper;
    use Cdek\Helpers\UrlHelper;

    class Frontend {
        public static function registerScripts(): void {
            if (!is_checkout()) {
                return;
            }

            Helper::enqueueScript('cdek-map', 'cdek-checkout-map', true);
            wp_localize_script('cdek-map', 'cdek_map', [
                'tmp_pvz_code' => UrlHelper::buildRest('/set-pvz-code-tmp'),
            ]);
        }

        public function __invoke(): void {
            add_action('wp_enqueue_scripts', [__CLASS__, 'registerScripts']);
        }
    }

}
