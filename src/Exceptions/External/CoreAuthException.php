<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions\External {

    use Cdek\Contracts\ExceptionContract;

    class CoreAuthException extends ExceptionContract
    {
        protected string $key = 'auth.core';
        protected int $status = 401;
        public function __construct()
        {
            parent::__construct('Failed to get shop token');
        }
    }
}
