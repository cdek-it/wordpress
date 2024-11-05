<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\Exceptions\External {

    use Cdek\Contracts\ExceptionContract;

    class ApiException extends ExceptionContract
    {
        protected string $key = 'api.general';
    }
}
