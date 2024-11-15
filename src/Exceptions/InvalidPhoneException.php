<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions {

    use Cdek\Contracts\ExceptionContract;

    class InvalidPhoneException extends ExceptionContract
    {
        protected string $key = 'validation.phone';

        public function __construct(string $phone)
        {
            $this->message = $this->message ?: sprintf(/* translators: %s: Recipient phone number */ esc_html__(
                'Incorrect phone number: %s',
                'cdekdelivery',
            ),
                $phone,
            );

            parent::__construct(
                [
                    'phone'   => $phone,
                ],
                false,
            );
        }
    }
}
