<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    class Note
    {

        public static function send(int $orderId, string $message, bool $notifyCustomer = false): void
        {
            $note  = '['.Loader::getPluginName().'] '.$message;
            $order = wc_get_order($orderId);
            $order->add_order_note($note, $notifyCustomer ? 1 : 0);
            $order->save();
        }

    }
}
