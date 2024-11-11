<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions {

    use Cdek\Contracts\ExceptionContract;

    class PhoneNotValidException extends ExceptionContract
    {
        protected string $key = 'phone.validation';

        public function __construct(string $invalidPhone, string $countryCode)
        {
            parent::__construct(
                sprintf(/* translators: %s: Recipient phone number */ esc_html__(
                    'Incorrect phone number: %s',
                    'cdekdelivery',
                ),
                    $invalidPhone,
                ),
                [
                    'phone'      => $invalidPhone,
                    'setCountry' => $countryCode,
                ],
                false,
            );
        }
    }
}
