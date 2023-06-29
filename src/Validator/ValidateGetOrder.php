<?php

namespace Cdek\Validator;

use Cdek\Model\Validate;

class ValidateGetOrder
{
    public static function validate($orderObj, $orderNumber, $orderId)
    {
        if ($orderObj->requests[0]->state === 'INVALID') {

            $note = '[CDEKDelivery] Попытка удаления заказа с номером '. $orderNumber . ' завершилась с ошибкой. Заказ не найден.';
            $order = wc_get_order($orderId);
            $order->add_order_note($note);
            $order->save();

            return new Validate(false, 'При удалении заказа произошла ошибка. Заказ c номером ' . $orderNumber . ' не найден.');
        }

        return new Validate(true);
    }
}