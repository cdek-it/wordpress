<?php

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\UI {

    use Cdek\Config;

    class AdminNotices
    {
        public static function weightUnitsConflict(): void
        {
            if (!(isset($_GET['section']) && $_GET['section'] === Config::DELIVERY_NAME) &&
                !((isset($_GET['tab']) && $_GET['tab'] === 'products'))) {
                return;
            }

            $measurement = esc_html(get_option('woocommerce_weight_unit'));
            if (!in_array($measurement, ['g', 'kg', 'lbs', 'oz'])) {
                echo "<div class='notice notice-info is-dismissible'><p>
            CDEKDelivery: Выбранная единица измерения веса ($measurement) не поддерживается данным плагином.
            Вы можете использовать значение для габаритов товара по умолчанию.
            Также вы можете обратиться в поддержку плагина для дополнительной информации.
            В противном случае, единица измерения будет автоматически обрабатываться как граммы.
            </p></div>";
            }
        }

        public function __invoke(): void
        {
            add_action('admin_notices', [__CLASS__, 'weightUnitsConflict']);
        }
    }

}
