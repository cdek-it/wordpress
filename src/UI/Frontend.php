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

            wp_set_script_translations('cdek-map', 'official-cdek', dirname( plugin_basename(__FILE__) ) . '/../../lang');
        }

        public function __invoke(): void {
            add_action('wp_enqueue_scripts', [__CLASS__, 'registerScripts']);
        }
    }

}
