<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Validator {

    use Cdek\Model\Validate;
    use Cdek\Note;

    class ValidateDeleteOrder
    {
        public static function validate($delete, $orderNumber, $orderId): Validate
        {
            if ($delete->requests[0]->state === 'INVALID') {

                $message = sprintf(
                    __("An attempt to delete order number \n\r%s \n\rfailed with an error. Error code: \n\r %s", 'cdekdelivery'),
                    $orderNumber, $delete->requests[0]->errors[0]->code);
                Note::send($orderId, $message);

                return new Validate(
                    false,
                    sprintf(
                        __(
                            "An error occurred while deleting the order. Order number \n\r%s \n\r was not deleted",
                            'cdekdelivery'
                        ),
                        $orderNumber
                    )
                );
            }

            return new Validate(true);
        }
    }
}
