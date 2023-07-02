<?php

namespace Cdek\Model;

class CourierMetaData
{
    public static function addMetaByOrderId($orderId, $data)
    {
        delete_post_meta($orderId, 'courier_data');
        add_post_meta($orderId, 'courier_data', $data);
    }

    public static function getMetaByOrderId($orderId)
    {
        $meta = get_post_meta($orderId, 'courier_data');
        if (empty($meta)) {
            return [];
        }
        return $meta[0];
    }

    public static function cleanMetaByOrderId($orderId)
    {
        $data = self::getMetaByOrderId($orderId);
        $data['courier_number'] = '';
        $data['courier_uuid'] = '';

        update_post_meta($orderId, 'courier_data', $data);
    }
}