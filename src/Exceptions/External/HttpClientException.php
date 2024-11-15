<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions\External {

    class HttpClientException extends ApiException
    {
        protected string $key = 'http.client';

        public function __construct(array $data)
        {
            $this->message = $this->message ?: esc_html__('Client request error', 'cdekdelivery');

            parent::__construct($data);
        }
    }
}
