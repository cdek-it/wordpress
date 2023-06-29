<?php

namespace Cdek\Validator;

use Cdek\Model\Validate;

class ValidateOrder
{
    public static function validate($orderData)
    {
        if ($orderData->requests[0]->state === 'INVALID') {
            return new Validate(false, 'Ошибка. Заказ не создан. (' . $orderData->requests[0]->errors[0]->message . ')');
        }

        return new Validate(true);
    }
}