<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Validator {

    use Cdek\Model\Validate;
    use Cdek\Note;

    class ValidateGetOrder
    {
        public static function validate($orderObj, $orderNumber, $orderId): Validate
        {
            if ($orderObj->requests[0]->state === 'INVALID') {

                $message =
                    'Попытка удаления заказа с номером ' . $orderNumber . ' завершилась с ошибкой. Заказ не найден.';
                Note::send($orderId, $message);

                return new Validate(false,
                                    'При удалении заказа произошла ошибка. Заказ c номером ' .
                                    $orderNumber .
                                    ' не найден.');
            }

            return new Validate(true);
        }
    }
}
