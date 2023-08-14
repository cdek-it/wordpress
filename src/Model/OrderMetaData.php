<?php

namespace Cdek\Model;

use Cdek\Config;

class OrderMetaData {
    public static function addMetaByOrderId($orderId, $data): void {
        add_post_meta($orderId, Config::META_KEY, $data);
    }

    public static function updateMetaByOrderId($orderId, $data): void {
        update_post_meta($orderId, Config::META_KEY, $data);
    }

    public static function cleanMetaByOrderId(int $order_id): void {
        $data                 = self::getMetaByOrderId($order_id);
        $data['order_number'] = '';
        $data['order_uuid']   = '';

        if (array_key_exists('cdek_order_uuid', $data)) {
            unset($data['cdek_order_uuid']);
        }
        if (array_key_exists('cdek_order_waybill', $data)) {
            unset($data['cdek_order_waybill']);
        }

        update_post_meta($order_id, Config::META_KEY, $data);
    }

    public static function getMetaByOrderId($orderId) {
        $meta = get_post_meta($orderId, Config::META_KEY);

        return $meta[0];
    }
}
