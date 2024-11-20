<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\Actions\FlushTokenCacheAction;
    use Cdek\CdekApi;
    use Cdek\Config;

    class SettingsController
    {
        public static function auth(): void
        {
            check_ajax_referer(Config::DELIVERY_NAME);

            if (!current_user_can('manage_woocommerce')) {
                wp_die(-2, 403);
            }

            if ((new CdekApi)->checkAuth()) {
                wp_send_json_success();
            }

            wp_send_json_error();
        }

        /**
         * @throws \Cdek\Exceptions\External\ApiException
         * @throws \Cdek\Exceptions\External\LegacyAuthException
         */
        public static function cities(): void
        {
            check_ajax_referer(Config::DELIVERY_NAME);

            if (!current_user_can('manage_woocommerce')) {
                wp_die(-2, 403);
            }

            /** @noinspection GlobalVariableUsageInspection */
            wp_send_json_success(
                (new CdekApi)->citySuggest(
                    sanitize_text_field(wp_unslash($_GET['q'])),
                    get_option('woocommerce_default_country', 'RU'),
                ),
            );
        }

        public static function cache(): void
        {
            check_ajax_referer(Config::DELIVERY_NAME);

            if (!current_user_can('manage_woocommerce')) {
                wp_die(-2, 403);
            }

            FlushTokenCacheAction::new()();

            wp_send_json_success();
        }

        public function __invoke(): void
        {
            if (!wp_doing_ajax()) {
                return;
            }

            $prefix = Config::DELIVERY_NAME;

            add_action("wp_ajax_$prefix-auth", [__CLASS__, 'auth']);
            add_action("wp_ajax_$prefix-cities", [__CLASS__, 'cities']);
            add_action("wp_ajax_$prefix-cache", [__CLASS__, 'cache']);
        }
    }
}
