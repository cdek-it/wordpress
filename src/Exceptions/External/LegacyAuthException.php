<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions\External {

    use Cdek\Contracts\ExceptionContract;

    /**
     * @deprecated use CoreAuthException instead
     */
    class LegacyAuthException extends ExceptionContract
    {
        protected string $key = 'auth.int';
        protected int $status = 401;

        public function __construct(array $data)
        {
            parent::__construct('Failed to get the token', $data);
        }
    }
}
