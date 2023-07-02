<?php

namespace Cdek\Validator;

use Cdek\Model\Validate;
use Cdek\Note;

class ValidateDeleteOrder
{
    public static function validate($delete, $orderNumber, $orderId)
    {
        if ($delete->requests[0]->state === 'INVALID') {

            $message = 'Попытка удаления заказа с номером '. $orderNumber . ' завершилась с ошибкой. Код ошибки: ' . $delete->requests[0]->errors[0]->code;
            Note::send($orderId, $message);

            return new Validate(false, 'При удалении заказа произошла ошибка. Заказ c номером ' . $orderNumber . ' не удален.');
        }

        return new Validate(true);
    }
}