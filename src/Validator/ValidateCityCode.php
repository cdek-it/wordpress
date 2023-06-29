<?php

namespace Cdek\Validator;

use Cdek\Model\Validate;

class ValidateCityCode
{
    public static function validate($cityCode)
    {
        if ($cityCode === -1) {
            return new Validate(false, 'Ошибка. Не удалось найти город отправки');
        }

        return new Validate(true);
    }
}