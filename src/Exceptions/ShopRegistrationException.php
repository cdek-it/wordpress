<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions {

    use Cdek\Contracts\ExceptionContract;

    class ShopRegistrationException extends ExceptionContract {
        protected string $key = 'shop.exchange';
    }
}
