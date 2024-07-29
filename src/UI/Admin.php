<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\UI {

    use Cdek\Config;
    use Cdek\Helper;
    use Cdek\Helpers\UrlHelper;
    use Cdek\Loader;

    class Admin
    {
        public static function addPluginLinks(array $links): array
        {
            array_unshift($links, '<a href="'.
                                  esc_url(admin_url('admin.php?page=wc-settings&tab=shipping&section='.
                                                    Config::DELIVERY_NAME)).
                                  '">'.
                                  esc_html__('Settings', 'cdekdelivery').
                                  '</a>');

            return $links;
        }

        public static function addPluginRowMeta(array $links, string $file): array
        {
            if ($file !== Loader::getPluginFile()) {
                return $links;
            }

            $links[] = '<a href="'.
                       esc_url(Config::DOCS_URL).
                       '" target="_blank">'.
                       esc_html__('Docs', 'cdekdelivery').
                       '</a>';

            $links[] = '<a href="'.
                       esc_url(Config::FAQ_URL).
                       '" target="_blank">'.
                       esc_html__('FAQ', 'cdekdelivery').
                       '</a>';

            return $links;
        }

        public static function registerAdminScripts(): void
        {
            // Not on Settings page.
            if (!isset($_GET['tab']) || $_GET['tab'] !== 'shipping') {
                return;
            }

            Helper::enqueueScript('cdek-admin-settings', 'cdek-admin-settings', true);
            wp_localize_script('cdek-admin-settings', 'cdek_admin_settings', [
                'api' => [
                    'offices'    => UrlHelper::buildRest('/get-offices'),
                    'check_auth' => UrlHelper::buildRest('/check-auth'),
                ],
            ]);
        }

        public function __invoke(): void
        {
            add_action('load-woocommerce_page_wc-settings', [__CLASS__, 'registerAdminScripts']);
        }
    }

}
