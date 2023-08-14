<?php

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek {

    use Cdek\Controllers\CourierController;
    use Cdek\Controllers\OrderController;
    use Cdek\Controllers\RestController;
    use Cdek\UI\Admin;
    use Cdek\UI\Frontend;
    use Cdek\UI\Leaflet;
    use RuntimeException;

    class Loader {
        public const REQUIRED_PLUGINS = [
            'WooCommerce' => [
                'entry'   => 'woocommerce/woocommerce.php',
                'version' => '7.0.0',
            ],
        ];
        private static string $pluginVersion;
        private static string $pluginMainFile;

        public static function getPluginVersion(): string {
            return self::$pluginVersion;
        }

        public static function getPluginUrl(): string {
            return plugin_dir_url(self::$pluginMainFile);
        }

        /**
         * @throws RuntimeException
         */
        public static function activate(): void {
            if (!current_user_can('activate_plugins')) {
                return;
            }

            self::checkRequirements();
        }

        /**
         * @throws RuntimeException
         */
        private static function checkRequirements(): void {
            $activePlugins = get_option('active_plugins');

            foreach (self::REQUIRED_PLUGINS as $plugin => $checkFields) {
                if (!in_array($checkFields['entry'], $activePlugins, true)) {
                    throw new RuntimeException("$plugin plugin is not activated, but required.");
                }
            }
        }

        public function __invoke(string $pluginMainFile): void {
            self::$pluginMainFile = $pluginMainFile;
            add_action('activate_cdek/cdek.php', [__CLASS__, 'activate']);

            try {
                self::checkRequirements();
            } catch (RuntimeException $e) {
                require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                \deactivate_plugins(self::$pluginMainFile);
                return;
            }

            self::$pluginVersion = get_file_data(self::$pluginMainFile, ['Version'])[0];

            add_action('rest_api_init', new RestController);
            add_action('rest_api_init', new OrderController);
            add_action('rest_api_init', new CourierController);

            add_action( 'before_woocommerce_init', function() {
                if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
                }
            } );

            (new Admin)();
            (new Leaflet)();
            (new Frontend)();
        }

    }

}
