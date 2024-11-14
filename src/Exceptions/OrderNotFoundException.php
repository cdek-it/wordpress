<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions {

    use Cdek\Contracts\ExceptionContract;

    class OrderNotFoundException extends ExceptionContract {
        protected string $key = 'order.missing';
        public function __construct()
        {
            parent::__construct('Order not found');
        }
    }
}
