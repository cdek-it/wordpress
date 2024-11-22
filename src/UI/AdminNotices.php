<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\UI {

    use Cdek\Config;
    use Cdek\Helpers\WeightConverter;
    use Cdek\Loader;
    use Cdek\Traits\CanBeCreated;

    class AdminNotices
    {
        use CanBeCreated;

        public static function weightUnitsConflict(): void
        {
            /** @noinspection GlobalVariableUsageInspection */
            if (!(isset($_GET['section']) && $_GET['section'] === Config::DELIVERY_NAME) &&
                !((isset($_GET['tab']) && $_GET['tab'] === 'products'))) {
                return;
            }

            $measurement = get_option('woocommerce_weight_unit');

            if (WeightConverter::isSupported($measurement)) {
                return;
            }

            echo '<div class="notice notice-info is-dismissible"><p> '.Loader::getPluginName().': '.sprintf(
                    __(
                        "The selected weight unit %s is not supported by this plugin.\nYou can use the default value for product dimensions.\nYou can also contact plugin support for more information.\nOtherwise, the unit of measurement will be automatically treated as grams.",
                        'cdekdelivery',
                    ),
                    esc_html($measurement),
                ).'</p></div>';

        }

        public function __invoke(): void
        {
            add_action('admin_notices', [__CLASS__, 'weightUnitsConflict']);
        }
    }
}
