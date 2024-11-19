<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions {

    use Cdek\Contracts\ExceptionContract;

    class ShippingNotFoundException extends ExceptionContract {
        protected string $key = 'shipping.missing';

        public function __construct()
        {
            $this->message = $this->message ?: esc_html__('Shipping not found', 'cdekdelivery');

            parent::__construct();
        }
    }
}
