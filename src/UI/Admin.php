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
                                  admin_url('admin.php?page=wc-settings&tab=shipping&section=cdek').
                                  '">'.
                                  esc_html__('Settings', 'official-cdek').
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
                       esc_html__('Docs', 'official-cdek').
                       '</a>';

            $links[] = '<a href="'.
                       esc_url(Config::FAQ_URL).
                       '" target="_blank">'.
                       esc_html__('FAQ', 'official-cdek').
                       '</a>';

            return $links;
        }

        public static function registerAdminScripts(): void
        {
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

        public static function registerOrderScripts(): void
        {
            Helper::enqueueScript('cdek-admin-create-order', 'cdek-create-order', true);
        }

        public function __invoke(): void
        {
            add_action('load-woocommerce_page_wc-settings', [__CLASS__, 'registerAdminScripts']);

            add_action('admin_enqueue_scripts', [__CLASS__, 'registerOrderScripts']);
        }
    }

}
