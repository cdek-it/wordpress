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

                $message
                    = sprintf(esc_html__(/* translators: %s: Order number */'An attempt to delete order number %s failed with an error. Order not found.',
                                         'cdekdelivery'), $orderNumber);

                Note::send($orderId, $message);

                return new Validate(false,
                                    sprintf(esc_html__(/* translators: %s: Order number */'An error occurred while deleting the order. Order number %s was not found.',
                                               'cdekdelivery'), $orderNumber));
            }

            return new Validate(true);
        }
    }
}
