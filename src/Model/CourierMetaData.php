<?php


namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Model {

    class CourierMetaData
    {
        public static function addMetaByOrderId(int $orderId, array $data): void
        {
            $order = wc_get_order($orderId);
            $order->delete_meta_data('courier_data');
            $order->add_meta_data('courier_data', $data, true);
            $order->save();
        }

        public static function cleanMetaByOrderId(int $orderId): void
        {
            $order = wc_get_order($orderId);
            $order->delete_meta_data('courier_data');
            $order->save();
        }

        public static function getMetaByOrderId(int $orderId): array
        {
            return wc_get_order($orderId)->get_meta('courier_data') ?: [];
        }
    }
}
