<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions\External {

    class HttpServerException extends ApiException {
        protected string $key = 'http.server';

        public function __construct(array $data)
        {
            $this->message = $this->message ?: esc_html__('Server request error', 'cdekdelivery');

            parent::__construct($data);
        }
    }
}
