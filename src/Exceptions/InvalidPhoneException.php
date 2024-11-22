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
            if (empty($phone)) {
                $this->message = $this->message ?: esc_html__('Recipient phone number is empty', 'cdekdelivery');
            } else {
                $this->message = $this->message ?: sprintf(/* translators: %s: Recipient phone number */ esc_html__(
                    'Incorrect recipient phone number: %s',
                    'cdekdelivery',
                ),
                    $phone,
                );
            }

            parent::__construct(
                [
                    'phone' => $phone,
                ],
            );
        }
    }
}
