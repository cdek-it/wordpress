<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\UI {

    use Cdek\Config;
    use Cdek\Helper;
    use Cdek\Helpers\UrlHelper;
    use Cdek\Loader;

    class Admin {
        public static function registerAdminScripts(): void {
            if (!isset($_GET['section']) || $_GET['section'] !== Config::DELIVERY_NAME) {
                return;
            }

            Helper::enqueueScript('cdek-admin-settings', 'cdek-admin-settings', true);
            wp_localize_script('cdek-admin-settings', 'cdek_admin_settings', [
                'api'   => [
                    'offices'    => UrlHelper::buildRest('/get-offices'),
                    'check_auth' => UrlHelper::buildRest('/check-auth'),
                ],
            ]);
        }

        public static function registerOrderScripts(): void {
            Helper::enqueueScript('cdek-admin-create-order', 'cdek-create-order', true);
        }

        public function __invoke(): void {
            add_action('load-woocommerce_page_wc-settings', [__CLASS__, 'registerAdminScripts']);

            add_action('admin_enqueue_scripts', [__CLASS__, 'registerOrderScripts']);
        }
    }

}
