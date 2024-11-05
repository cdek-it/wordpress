<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Validator {

    use Cdek\Model\ValidationResult;
    use Cdek\Note;

    class ValidateDeleteOrder
    {
        public static function validate($delete, $orderNumber, $orderId): ValidationResult
        {
            if ($delete['requests'][0]['state'] === 'INVALID') {
                $message
                    = sprintf(
                    esc_html__(/* translators: %s: Order number */
                        'An attempt to delete order number %s failed with an error. Error code: %s',
                        'cdekdelivery',
                    ),
                    $orderNumber,
                    $delete->requests[0]->errors[0]->code,
                );
                Note::send($orderId, $message);

                return new ValidationResult(
                    false, sprintf(
                        esc_html__(/* translators: %s: Order number */
                            'An error occurred while deleting the order. Order number %s was not deleted.',
                            'cdekdelivery',
                        ),
                        $orderNumber,
                    ),
                );
            }

            return new ValidationResult(true);
        }
    }
}
