<?php

namespace Cdek\Validator;

use Cdek\Model\Validate;

class ValidateDeleteOrder
{
    public static function validate($delete, $orderNumber, $orderId)
    {
        if ($delete->requests[0]->state === 'INVALID') {

            $note = '[CDEKDelivery] Попытка удаления заказа с номером '. $orderNumber . ' завершилась с ошибкой. Код ошибки: '.
                $delete->requests[0]->errors[0]->code;
            $order = wc_get_order($orderId);
            $order->add_order_note($note);
            $order->save();

            return new Validate(false, 'При удалении заказа произошла ошибка. Заказ c номером ' . $orderNumber . ' не удален.');
        }

        return new Validate(true);
    }
}