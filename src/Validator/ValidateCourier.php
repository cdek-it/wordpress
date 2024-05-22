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
                                    sprintf(/* translators: %s: Error message */ esc_html__('Error. The courier request has not been created. (%s)',
                                                                                            'cdekdelivery'),
                                                                                 $courierData->requests[0]->errors[0]->message));
            }

            return new Validate(true);
        }

        public static function validateExist($callCourier): Validate
        {
            if ($callCourier->requests[0]->type === 'DELETE' && $callCourier->requests[0]->state === 'SUCCESSFUL') {
                return new Validate(false, esc_html__('Application deleted', 'cdekdelivery'));
            }

            return new Validate(true);
        }
    }
}
