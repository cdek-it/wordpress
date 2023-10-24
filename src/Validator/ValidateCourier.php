<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Validator {

    use Cdek\Model\Validate;

    class ValidateCourier
    {
        public static function validate($courierData): Validate
        {
            if ($courierData->requests[0]->state === 'INVALID') {
                return new Validate(false,
                                    'Ошибка. Заявка на вызов курьера не создана. (' .
                                    $courierData->requests[0]->errors[0]->message .
                                    ')');
            }

            return new Validate(true);
        }

        public static function validateExist($callCourier): Validate
        {
            if ($callCourier->requests[0]->type === 'DELETE' && $callCourier->requests[0]->state === 'SUCCESSFUL') {
                return new Validate(false, 'Заявка удалена');
            }

            return new Validate(true);
        }
    }
}
