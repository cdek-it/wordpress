<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    use Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry;
    use Automattic\WooCommerce\Utilities\FeaturesUtil;
    use Cdek\Actions\CreateOrderAction;
    use Cdek\Actions\DispatchOrderAutomationAction;
    use Cdek\Actions\FlushTokenCacheAction;
    use Cdek\Actions\ProcessWoocommerceCreateShippingAction;
    use Cdek\Actions\RecalculateShippingAction;
    use Cdek\Actions\SaveCustomCheckoutFieldsAction;
    use Cdek\Managers\TaskManager;
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

    class Loader
    {
        public const REQUIRED_PLUGINS
            = [
                'WooCommerce' => [
                    'entry'   => 'woocommerce/woocommerce.php',
                    'version' => '6.9.0',
                ],
            ];
        public const EXTENSIONS
            = [
                'openssl',
            ];
        private static string $pluginVersion;
        private static string $pluginMainFilePath;

        private static string $pluginName;
        private static string $pluginMainFile;

        public static function getPluginVersion(): string
        {
            return self::$pluginVersion;
        }

        public static function getPluginName(): string
        {
            return self::$pluginName;
        }

        public static function getPluginUrl(): string
        {
            return plugin_dir_url(self::$pluginMainFilePath);
        }

        public static function getPluginFile(): string
        {
            return self::$pluginMainFile;
        }

        /**
         * @throws RuntimeException
         */
        public static function activate(): void
        {
            if (!current_user_can('activate_plugins')) {
                return;
            }

            self::checkRequirements();
            TaskManager::addPluginScheduleEvents();
        }

        public static function deactivate()
        {
            foreach (TaskManager::getTasksHooks() as $hook){
                if (as_has_scheduled_action($hook) !== false) {
                    as_unschedule_action($hook);
                }
            }
        }

        /**
         * @throws RuntimeException
         */
        private static function checkRequirements(): void
        {
            $activePlugins = get_option('active_plugins');

            foreach (self::REQUIRED_PLUGINS as $plugin => $checkFields) {
                if (!in_array($checkFields['entry'], $activePlugins, true)) {
                    throw new RuntimeException("$plugin plugin is not activated, but required.");
                }

                if (version_compare($checkFields['version'],
                                    get_file_data(WP_PLUGIN_DIR.'/'.$checkFields['entry'], ['Version'])[0], '>')) {
                    throw new RuntimeException("$plugin plugin version is too old, required minimum version is {$checkFields['version']}.");
                }
            }

            foreach (self::EXTENSIONS as $extension) {
                if (!extension_loaded($extension)) {
                    throw new RuntimeException("$extension is not enabled, but required.");
                }
            }
        }

        public static function getPluginPath(): string
        {
            return plugin_dir_path(self::$pluginMainFilePath);
        }

        public function __invoke(string $pluginMainFile): void
        {
            self::$pluginMainFilePath = $pluginMainFile;

            try {
                self::checkRequirements();
            } catch (RuntimeException $e) {
                require_once(ABSPATH.'wp-admin/includes/plugin.php');
                deactivate_plugins(self::$pluginMainFilePath);

                return;
            }

            self::$pluginVersion  = get_file_data(self::$pluginMainFilePath, ['Version'])[0];
            self::$pluginName     = get_file_data(self::$pluginMainFilePath, ['Plugin Name'])[0];
            self::$pluginMainFile = plugin_basename(self::$pluginMainFilePath);

            add_action("activate_" . plugin_basename($pluginMainFile), [__CLASS__, 'activate']);
            add_action("deactivate_" . plugin_basename($pluginMainFile), [__CLASS__, 'deactivate']);

            self::declareCompatibility();

            add_action('plugins_loaded',
                static fn() => load_plugin_textdomain('cdekdelivery', false, dirname(self::$pluginMainFile).'/lang'));

            add_filter('plugin_action_links_'.self::$pluginMainFile, [Admin::class, 'addPluginLinks']);
            add_filter('plugin_row_meta', [Admin::class, 'addPluginRowMeta'], 10, 2);

            add_action('rest_api_init', new RestController);
            add_action('rest_api_init', new OrderController);
            add_action('rest_api_init', new CourierController);
            add_action('rest_api_init', new LocationController);

            add_filter('woocommerce_hidden_order_itemmeta', [DataWPScraber::class, 'hideMeta']);
            add_filter('woocommerce_checkout_fields', [CheckoutHelper::class, 'restoreCheckoutFields'], 1090);
            add_action('woocommerce_shipping_methods',
                static fn($methods) => array_merge($methods, [Config::DELIVERY_NAME => CdekShippingMethod::class]));

            add_action('woocommerce_checkout_process', new CheckoutProcessValidator);
            add_action('woocommerce_store_api_checkout_update_order_meta', new CheckoutProcessValidator);
            add_action('woocommerce_order_before_calculate_totals', new RecalculateShippingAction, 10, 2);

            add_action('woocommerce_after_shipping_rate', new CheckoutMap, 10, 2);
            add_filter('woocommerce_checkout_create_order_shipping_item', new ProcessWoocommerceCreateShippingAction);
            add_action('woocommerce_checkout_create_order', new SaveCustomCheckoutFieldsAction, 10, 2);
            add_action('woocommerce_order_payment_status_changed', new DispatchOrderAutomationAction);
            add_action('woocommerce_checkout_order_processed', new DispatchOrderAutomationAction, 10, 3);
            add_action('woocommerce_store_api_checkout_order_processed', new DispatchOrderAutomationAction);

            add_action('woocommerce_blocks_loaded',
                static fn() => add_action('woocommerce_blocks_checkout_block_registration',
                    static fn(IntegrationRegistry $registry) => $registry->register(new CheckoutMapBlock)));

            add_action('woocommerce_blocks_loaded', [CheckoutMapBlock::class, 'addStoreApiData']);

            add_action('woocommerce_store_api_checkout_update_customer_from_request',
                       [CheckoutMapBlock::class, 'saveCustomerData'], 10, 2);

            add_action('woocommerce_store_api_checkout_update_order_from_request',
                       [CheckoutMapBlock::class, 'saveOrderData'], 10, 2);

            add_action('woocommerce_before_order_itemmeta', new AdminShippingFields, 10, 2);

            add_action('upgrader_process_complete', [TaskManager::class, 'addPluginScheduleEvents']);

            add_action(Config::ORDER_AUTOMATION_HOOK_NAME, new CreateOrderAction, 10, 2);

            TaskManager::registerTasks();

            (new CdekWidget)();
            (new Admin)();
            (new Frontend)();
            (new MetaBoxes)();
            (new AdminNotices)();
        }

        private static function declareCompatibility(): void
        {
            add_action('before_woocommerce_init', static function () {
                if (class_exists(FeaturesUtil::class)) {
                    FeaturesUtil::declare_compatibility('custom_order_tables', self::$pluginMainFilePath, true);
                    FeaturesUtil::declare_compatibility('cart_checkout_blocks', self::$pluginMainFilePath, true);
                }
            });
        }
    }
}
