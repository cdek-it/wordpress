<?php

namespace Cdek\Model;

class OrderMetaData
{
    public static function addMetaByOrderId($orderId, $data)
    {
        add_post_meta($orderId, CDEK_META_KEY, $data);
    }

    public static function getMetaByOrderId($orderId)
    {
        $meta = get_post_meta($orderId, CDEK_META_KEY);
        return $meta[0];
    }

    public static function updateMetaByOrderId($orderId, $data)
    {
        update_post_meta($orderId, CDEK_META_KEY, $data);
    }
}