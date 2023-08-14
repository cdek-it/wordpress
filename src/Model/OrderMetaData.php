<?php

namespace Cdek\Model;

use Cdek\Config;

class OrderMetaData {
    public static function addMetaByOrderId($orderId, $data): void {
        $order = wc_get_order( $orderId );
        $order->add_meta_data(Config::META_KEY, $data, true);
        $order->save();
    }

    public static function updateMetaByOrderId($orderId, $data): void {
        $order = wc_get_order( $orderId );
        $order->update_meta_data(Config::META_KEY, $data);
        $order->save();
    }

    public static function cleanMetaByOrderId(int $orderId): void {
        $order = wc_get_order( $orderId );
        $order->delete_meta_data(Config::META_KEY);
        $order->save();
    }

    public static function getMetaByOrderId($orderId) {
        return wc_get_order( $orderId )->get_meta(Config::META_KEY);
    }
}
