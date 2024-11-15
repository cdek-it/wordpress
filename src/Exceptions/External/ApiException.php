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

        public function __construct(array $data, ?string $message = null)
        {
            $this->message = $this->message ?: $message ?: esc_html__('API error', 'cdekdelivery');

            parent::__construct($data);
        }
    }
}
