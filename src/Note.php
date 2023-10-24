<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    class Note
    {

        public static function send(int $orderId, string $message): void
        {
            $note = '[CDEKDelivery] ' . $message;
            $order = wc_get_order($orderId);
            $order->add_order_note($note);
            $order->save();
        }

    }
}
