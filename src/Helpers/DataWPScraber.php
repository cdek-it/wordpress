<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\MetaKeys;

    class DataWPScraber
    {
        public static function getData($data, array $params): array
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

            if ((isset($_REQUEST['page'], $_REQUEST['action'], $_REQUEST['id']) &&
                 $_REQUEST['page'] === 'wc-orders' &&
                 $_REQUEST['action'] === 'edit' &&
                 CheckoutHelper::isCdekShippingMethod(wc_get_order(absint(wp_strip_all_tags($_REQUEST['id']))))) ||
                (isset($_REQUEST['action'], $_REQUEST['order_id']) &&
                 $_REQUEST['action'] === 'woocommerce_load_order_items' &&
                 CheckoutHelper::isCdekShippingMethod(wc_get_order(absint(wp_strip_all_tags($_REQUEST['order_id'])))))) {
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
    }
}
