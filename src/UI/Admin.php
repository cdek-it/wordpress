<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\UI {

    use Cdek\Config;
    use Cdek\Helpers\UI;
    use Cdek\Loader;
    use Cdek\ShippingMethod;
    use Cdek\Traits\CanBeCreated;

    class Admin
    {
        use CanBeCreated;

        public static function addPluginLinks(array $links): array
        {
            array_unshift(
                $links,
                '<a href="'.esc_url(
                    admin_url(
                        'admin.php?page=wc-settings&tab=shipping&section='.Config::DELIVERY_NAME,
                    ),
                ).'">'.esc_html__('Settings', 'cdekdelivery').'</a>',
            );

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
            global $current_section, $current_tab;

            // Is not shipping settings page.
            if ($current_tab !== 'shipping') {
                return;
            }

            if ($current_section !== Config::DELIVERY_NAME) {
                $shippingMethodCurrent =
                    \WC_Shipping_Zones::get_shipping_method(absint(wp_unslash($_REQUEST['instance_id'])));

                // Is not CDEK shipping page
                if ($shippingMethodCurrent === false || $shippingMethodCurrent->id !== Config::DELIVERY_NAME) {
                    return;
                }
            }

            UI::enqueueScript('cdek-admin-settings', 'cdek-admin-settings', true, false, true);
        }

        public function __invoke(): void
        {
            add_action('woocommerce_settings_start', [__CLASS__, 'registerAdminScripts']);
        }
    }
}
