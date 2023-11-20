<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Model {

    use Cdek\Config;

    class OrderMetaData
    {
        public static function addMetaByOrderId(int $orderId, array $data): void
        {
            $order = wc_get_order($orderId);
            $order->add_meta_data(Config::META_KEY, $data, true);
            $order->save();
        }

        public static function updateMetaByOrderId(int $orderId, array $data): void
        {
            $order = wc_get_order($orderId);
            $order->update_meta_data(Config::META_KEY, array_merge($order->get_meta(Config::META_KEY) ?: [], $data));
            $order->save();
        }

        public static function cleanMetaByOrderId(int $orderId): void
        {
            $order = wc_get_order($orderId);

            $meta = $order->get_meta(Config::META_KEY) ?: [];

            $meta['order_number'] = '';
            $meta['order_uuid'] = '';

            unset($meta['cdek_order_uuid'], $meta['cdek_order_waybill']);

            $order->update_meta_data(Config::META_KEY, $meta);
            $order->save();
        }

        public static function getMetaByOrderId(int $orderId): array
        {
            return wc_get_order($orderId)->get_meta(Config::META_KEY) ?: [];
        }
    }
}
