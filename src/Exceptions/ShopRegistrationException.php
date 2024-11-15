<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions {

    use Cdek\Contracts\ExceptionContract;

    class ShopRegistrationException extends ExceptionContract {
        protected string $key = 'shop.exchange';

        public function __construct()
        {
            $this->message = $this->message ?: esc_html__('Shop registration error', 'cdekdelivery');

            parent::__construct();
        }
    }
}
