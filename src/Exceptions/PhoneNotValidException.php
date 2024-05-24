<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions {

    class PhoneNotValidException extends CdekException
    {

        public function __construct(string $invalidPhone, string $countryCode)
        {
            parent::__construct(sprintf(/* translators: %s: Recipient phone number */ esc_html__('Incorrect phone number: %s',
                                                                                                'cdekdelivery'),
                                                                                     $invalidPhone),
                'cdek_error.phone.validation', [
                                    'phone' => $invalidPhone,
                                                                  'setCountry' => $countryCode,
                                ], false);
        }
    }
}
