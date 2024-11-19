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
            $this->message = $this->message ?: esc_html__('Core auth error', 'cdekdelivery');

            parent::__construct();
        }
    }
}
