<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    use Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry;
    use Automattic\WooCommerce\Utilities\FeaturesUtil;
    use Cdek\Actions\DispatchOrderAutomationAction;
    use Cdek\Actions\OrderCreateAction;
    use Cdek\Actions\ProcessWoocommerceCreateShippingAction;
    use Cdek\Actions\RecalculateShippingAction;
    use Cdek\Actions\SaveCustomCheckoutFieldsAction;
    use Cdek\Actions\SaveOfficeToSessionAction;
    use Cdek\Blocks\AdminOrderBox;
    use Cdek\Blocks\CheckoutMapBlock;
    use Cdek\Controllers\CallbackController;
    use Cdek\Controllers\IntakeController;
    use Cdek\Controllers\OrderController;
    use Cdek\Controllers\OrderItemController;
    use Cdek\Controllers\SettingsController;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\Helpers\DataCleaner;
    use Cdek\Traits\CanBeCreated;
    use Cdek\UI\Admin;
    use Cdek\UI\AdminNotices;
    use Cdek\UI\AdminOrderProductFields;
    use Cdek\UI\AdminShippingFields;
    use Cdek\UI\CdekWidget;
    use Cdek\UI\CheckoutMap;
    use Cdek\UI\Frontend;
    use Cdek\Validator\CheckoutValidator;
    use RuntimeException;

    class Loader
    {
        use CanBeCreated;

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

        public const MIGRATORS
            = [
                Migrators\MigrateCityCodeFromMap::class,
            ];

        private static bool $debug;
        private static string $pluginVersion;
        private static string $pluginMainFilePath;

        private static string $pluginName;
        private static string $pluginMainFile;

        /**
         * @throws RuntimeException
         */
        public static function activate(): void
        {
            if (!current_user_can('activate_plugins')) {
                return;
            }

            self::checkRequirements();
            self::upgrade();
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

                if (version_compare(
                    $checkFields['version'],
                    get_file_data(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $checkFields['entry'], ['Version'])[0],
                    '>',
                )) {
                    throw new RuntimeException(
                        "$plugin plugin version is too old, required minimum version is {$checkFields['version']}.",
                    );
                }
            }

            foreach (self::EXTENSIONS as $extension) {
                if (!extension_loaded($extension)) {
                    throw new RuntimeException("$extension is not enabled, but required.");
                }
            }
        }

        public static function upgrade(): void
        {
            TaskManager::scheduleExecution();

            foreach (self::MIGRATORS as $migrator) {
                (new $migrator)();
            }
        }

        public static function deactivate(): void
        {
            TaskManager::cancelExecution();
        }

        public static function debug(): bool
        {
            return self::$debug;
        }

        public static function getPluginFile(): string
        {
            return self::$pluginMainFile;
        }

        public static function getPluginName(): string
        {
            return self::$pluginName;
        }

        public static function getPluginUrl(?string $path = null): string
        {
            return plugin_dir_url(self::$pluginMainFilePath) . ($path !== null ? ltrim($path, '/') : '');
        }

        public static function getPluginVersion(): string
        {
            return self::$pluginVersion;
        }

        public static function getTemplate(string $name): string
        {
            return self::getPluginPath("templates/$name.php");
        }

        public static function getPluginPath(?string $path = null): string
        {
            return plugin_dir_path(self::$pluginMainFilePath) .
                   ($path !== null ? ltrim($path, DIRECTORY_SEPARATOR) : '');
        }

        /** @noinspection MissingParameterTypeDeclarationInspection */
        public static function scheduleUpgrade($wp, array $options): void
        {
            if ($options['type'] !== 'plugin') {
                return;
            }

            if ($options['action'] === 'install' && $wp->new_plugin_data['Name'] !== self::$pluginName) {
                return;
            }

            if (($options['action'] === 'update' &&
                 !in_array(self::$pluginMainFile, $options['plugins'] ?? [], true))) {
                return;
            }

            as_schedule_single_action(time() + 60, Config::UPGRADE_HOOK_NAME, [], 'cdekdelivery', true);
        }

        public function __invoke(string $pluginMainFile): void
        {
            self::$pluginMainFilePath = $pluginMainFile;
            /** @noinspection GlobalVariableUsageInspection */
            self::$debug = isset($_GET[Config::MAGIC_KEY]);

            ExceptionHandler::new()();

            try {
                self::checkRequirements();
            } catch (RuntimeException $e) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
                deactivate_plugins(self::$pluginMainFilePath);

                return;
            }

            self::$pluginVersion  = get_file_data(self::$pluginMainFilePath, ['Version'])[0];
            self::$pluginName     = get_file_data(self::$pluginMainFilePath, ['Plugin Name'])[0];
            self::$pluginMainFile = plugin_basename(self::$pluginMainFilePath);

            add_action("activate_" . self::$pluginMainFile, [__CLASS__, 'activate']);
            add_action("deactivate_" . self::$pluginMainFile, [__CLASS__, 'deactivate']);

            add_action('upgrader_process_complete', [__CLASS__, 'scheduleUpgrade'], 30, 2);

            self::declareCompatibility();

            add_action(
                'plugins_loaded',
                static fn() => load_plugin_textdomain('cdekdelivery', false, dirname(self::$pluginMainFile) . '/lang'),
            );

            add_filter('plugin_action_links_' . self::$pluginMainFile, [Admin::class, 'addPluginLinks']);
            add_filter('plugin_row_meta', [Admin::class, 'addPluginRowMeta'], 10, 2);

            add_action('rest_api_init', new CallbackController);

            add_action('admin_init', new IntakeController);
            add_action('admin_init', new OrderController);
            add_action('admin_init', new SettingsController);
            add_action('admin_init', new OrderItemController);

            add_action('wc_ajax_' . Config::DELIVERY_NAME . '_save-office', new SaveOfficeToSessionAction);

            add_filter('woocommerce_hidden_order_itemmeta', [DataCleaner::class, 'hideMeta']);
            add_filter('woocommerce_checkout_fields', [CheckoutHelper::class, 'restoreFields'], 1090);
            add_action(
                'woocommerce_shipping_methods',
                static fn(array $methods) => array_merge($methods, [Config::DELIVERY_NAME => ShippingMethod::class]),
            );

            add_action('woocommerce_checkout_process', new CheckoutValidator);
            add_action('woocommerce_store_api_checkout_update_order_meta', new CheckoutValidator);
            add_action('woocommerce_order_before_calculate_totals', new RecalculateShippingAction, 10, 2);

            add_action('woocommerce_after_shipping_rate', new CheckoutMap);
            add_filter('woocommerce_checkout_create_order_shipping_item', new ProcessWoocommerceCreateShippingAction);
            add_action('woocommerce_checkout_create_order', new SaveCustomCheckoutFieldsAction, 10, 2);
            add_action('woocommerce_order_status_changed', new DispatchOrderAutomationAction);
            add_action('woocommerce_checkout_order_processed', new DispatchOrderAutomationAction, 10, 3);
            add_action('woocommerce_store_api_checkout_order_processed', new DispatchOrderAutomationAction);

            add_action(
                'woocommerce_blocks_loaded',
                static fn() => add_action(
                    'woocommerce_blocks_checkout_block_registration',
                    static fn(IntegrationRegistry $registry) => $registry->register(new CheckoutMapBlock),
                ),
            );

            add_action('woocommerce_blocks_loaded', [CheckoutMapBlock::class, 'addStoreApiData']);

            add_action(
                'woocommerce_store_api_checkout_update_customer_from_request',
                [CheckoutMapBlock::class, 'saveCustomerData'],
                10,
                2,
            );

            add_action(
                'woocommerce_store_api_checkout_update_order_from_request',
                [CheckoutMapBlock::class, 'saveOrderData'],
                10,
                2,
            );

            add_action('woocommerce_before_order_itemmeta', new AdminShippingFields, 10, 2);
            add_action('woocommerce_after_order_itemmeta', new AdminOrderProductFields, 20, 3);

            add_action(Config::ORDER_AUTOMATION_HOOK_NAME, OrderCreateAction::new(), 10, 2);
            add_action(Config::TASK_MANAGER_HOOK_NAME, new TaskManager, 20);
            add_action(Config::UPGRADE_HOOK_NAME, [__CLASS__, 'upgrade']);

            CdekWidget::new()();
            Admin::new()();
            Frontend::new()();
            AdminOrderBox::new()();
            AdminNotices::new()();
        }

        private static function declareCompatibility(): void
        {
            add_action(
                'before_woocommerce_init',
                static function () {
                    if (class_exists(FeaturesUtil::class)) {
                        FeaturesUtil::declare_compatibility('custom_order_tables', self::$pluginMainFilePath, true);
                        FeaturesUtil::declare_compatibility('cart_checkout_blocks', self::$pluginMainFilePath, true);
                    }
                },
            );
        }
    }
}
