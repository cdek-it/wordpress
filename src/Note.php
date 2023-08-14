<?php

namespace Cdek;

class Note {

    public static function send($orderId, $message): void {
        $note  = '[CDEKDelivery] '.$message;
        $order = wc_get_order($orderId);
        $order->add_order_note($note);
        $order->save();
    }

}
