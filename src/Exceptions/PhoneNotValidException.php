<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions {

    class PhoneNotValidException extends CdekException
    {

        public function __construct(string $invalidPhone, string $countryCode)
        {
            parent::__construct('Incorrect phone number', 'cdek_error.phone.validation', [
                'phone' => $invalidPhone,
                'setCountry' => $countryCode
            ], false);
        }
    }
}
