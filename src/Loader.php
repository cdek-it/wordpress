<?php

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek {

    use Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry;
    use Automattic\WooCommerce\Utilities\FeaturesUtil;
    use Cdek\Actions\CreateOrderAction;
    use Cdek\Actions\DispatchOrderAutomationAction;
    use Cdek\Actions\ProcessWoocommerceCreateShippingAction;
    use Cdek\Actions\RecalculateShippingAction;
    use Cdek\Actions\SaveCustomCheckoutFieldsAction;
    use Cdek\Blocks\CheckoutMapBlock;
    use Cdek\Controllers\CourierController;
    use Cdek\Controllers\LocationController;
    use Cdek\Controllers\OrderController;
    use Cdek\Controllers\RestController;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\Helpers\DataWPScraber;
    use Cdek\UI\Admin;
    use Cdek\UI\AdminNotices;
    use Cdek\UI\AdminShippingFields;
    use Cdek\UI\CdekWidget;
    use Cdek\UI\CheckoutMap;
    use Cdek\UI\Frontend;
    use Cdek\UI\MetaBoxes;
    use Cdek\Validator\CheckoutProcessValidator;
    use RuntimeException;
    use function deactivate_plugins;

    class Loader {
        public const REQUIRED_PLUGINS = [
            'WooCommerce' => [
                'entry'   => 'woocommerce/woocommerce.php',
                'version' => '6.9.0',
            ],
        ];
        public const EXTENSIONS = [
            'openssl',
            'curl',
        ];
        private static string $pluginVersion;
        private static string $pluginMainFile;

        private static string $pluginName;

        public static function getPluginVersion(): string {
            return self::$pluginVersion;
        }

        public static function getPluginName(): string {
            return self::$pluginName;
        }

        public static function getPluginUrl(): string {
            return plugin_dir_url(self::$pluginMainFile);
        }

        public static function getPluginPath(): string {
            return plugin_dir_path(self::$pluginMainFile);
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

                if (version_compare($checkFields['version'], get_file_data(WP_PLUGIN_DIR. '/'. $checkFields['entry'],
                                                                           ['Version'])[0], '>')) {
                    throw new RuntimeException("$plugin plugin version is too old, required minimum version is {$checkFields['version']}.");
                }
            }

            foreach (self::EXTENSIONS as $extension) {
                if (!extension_loaded($extension)) {
                    throw new RuntimeException("$extension is not enabled, but required.");
                }
            }

        }

        public function __invoke(string $pluginMainFile): void {
            self::$pluginMainFile = $pluginMainFile;
            add_action("activate_$pluginMainFile", [__CLASS__, 'activate']);

            try {
                self::checkRequirements();
            } catch (RuntimeException $e) {
                require_once(ABSPATH.'wp-admin/includes/plugin.php');
                deactivate_plugins(self::$pluginMainFile);

                return;
            }

            self::$pluginVersion = get_file_data(self::$pluginMainFile, ['Version'])[0];
            self::$pluginName    = get_file_data(self::$pluginMainFile, ['Plugin Name'])[0];

            self::declareCompatibility();

            add_action('rest_api_init', new RestController);
            add_action('rest_api_init', new OrderController);
            add_action('rest_api_init', new CourierController);
            add_action('rest_api_init', new LocationController);

            add_filter('woocommerce_hidden_order_itemmeta', [DataWPScraber::class, 'hideMeta']);
            add_filter('woocommerce_checkout_fields', [CheckoutHelper::class, 'restoreCheckoutFields'], 1090);
            add_action('woocommerce_shipping_methods',
                static fn($methods) => array_merge($methods, ['official_cdek' => CdekShippingMethod::class]));

            add_action('woocommerce_checkout_process', new CheckoutProcessValidator);
            add_action('woocommerce_order_before_calculate_totals', new RecalculateShippingAction, 10, 2);

            add_action('woocommerce_after_shipping_rate', new CheckoutMap, 10, 2);
            add_filter('woocommerce_checkout_create_order_shipping_item', new ProcessWoocommerceCreateShippingAction);
            add_action('woocommerce_checkout_create_order', new SaveCustomCheckoutFieldsAction, 10, 2);
            add_action('woocommerce_checkout_order_processed', new DispatchOrderAutomationAction, 10, 3);

            add_action('woocommerce_blocks_loaded',
                static fn() => add_action('woocommerce_blocks_checkout_block_registration',
                    static fn(IntegrationRegistry $registry) => $registry->register(new CheckoutMapBlock)));

            add_action('woocommerce_blocks_loaded', [CheckoutMapBlock::class, 'addStoreApiData']);

            add_action('woocommerce_store_api_checkout_update_order_from_request', [CheckoutMapBlock::class, 'saveOrderData'], 10, 2);

            add_action('woocommerce_before_order_itemmeta', new AdminShippingFields, 10, 2);

            add_action(Config::ORDER_AUTOMATION_HOOK_NAME, new CreateOrderAction, 10, 2);

            (new CdekWidget)();
            (new Admin)();
            (new Frontend)();
            (new MetaBoxes)();
            (new AdminNotices)();
        }

        private static function declareCompatibility(): void {
            add_action('before_woocommerce_init', static function () {
                if (class_exists(FeaturesUtil::class)) {
                    FeaturesUtil::declare_compatibility('custom_order_tables', self::$pluginMainFile, true);
                    FeaturesUtil::declare_compatibility('cart_checkout_blocks', self::$pluginMainFile, true);
                }
            });
        }

    }

}
