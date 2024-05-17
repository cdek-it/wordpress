<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions {

    class PhoneNotValidException extends CdekException
    {

        public function __construct(string $invalidPhone, string $countryCode)
        {
            parent::__construct(sprintf(/* translators: 1: Recipient phone number */__('Incorrect phone number: %1$s',
                'cdekdelivery'), $invalidPhone), 'cdek_error.phone.validation', 
            [
                'phone' => $invalidPhone,
                'setCountry' => $countryCode,
            ], false);
        }
    }
}
