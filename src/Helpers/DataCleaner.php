<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\MetaKeys;
    use Cdek\Model\Order;
    use WP_REST_Request;

    class DataCleaner
    {
        public static function getData(WP_REST_Request $data, array $params): array
        {
            $result = [];
            foreach ($params as $param) {
                $result[$param] = $data->get_param($param);
            }

            return $result;
        }

        public static function hideMeta(array $hiddenMeta): array
        {
            $hiddenMeta[] = MetaKeys::ADDRESS_HASH;
            $hiddenMeta[] = MetaKeys::WEIGHT;
            $hiddenMeta[] = MetaKeys::LENGTH;
            $hiddenMeta[] = MetaKeys::WIDTH;
            $hiddenMeta[] = MetaKeys::HEIGHT;
            $hiddenMeta[] = MetaKeys::TARIFF_CODE;
            $hiddenMeta[] = MetaKeys::TARIFF_MODE;
            $hiddenMeta[] = MetaKeys::OFFICE_CODE;

            if (self::isOldOrderPage() || self::isNewOrderPage()) {
                $hiddenMeta[] = 'tariff_code';
                $hiddenMeta[] = 'tariff_type';
                $hiddenMeta[] = 'tariff_mode';
                $hiddenMeta[] = 'length';
                $hiddenMeta[] = 'width';
                $hiddenMeta[] = 'height';
                $hiddenMeta[] = 'pvz';
                $hiddenMeta[] = 'weight';
                $hiddenMeta[] = 'weight (kg)';
                $hiddenMeta[] = 'weight (g)';
                $hiddenMeta[] = 'weight (lbs)';
                $hiddenMeta[] = 'weight (oz)';
            }

            return $hiddenMeta;
        }

        /** @noinspection GlobalVariableUsageInspection */
        private static function isOldOrderPage(): bool
        {
            return isset($_REQUEST['page'], $_REQUEST['action'], $_REQUEST['id']) &&
                   $_REQUEST['page'] === 'wc-orders' &&
                   $_REQUEST['action'] === 'edit' &&
                   (new Order(
                       absint(wp_strip_all_tags($_REQUEST['id'])),
                   ))->getShipping() !== null;
        }

        /** @noinspection GlobalVariableUsageInspection */
        private static function isNewOrderPage(): bool
        {
            return isset($_REQUEST['action'], $_REQUEST['order_id']) &&
                   $_REQUEST['action'] === 'woocommerce_load_order_items' &&
                   (new Order(
                       absint(wp_strip_all_tags($_REQUEST['order_id'])),
                   ))->getShipping() !== null;
        }
    }
}
