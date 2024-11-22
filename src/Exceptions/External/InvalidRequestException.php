<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\Exceptions\External {
    class InvalidRequestException extends HttpClientException
    {
        protected string $key = 'http.validation';

        public function __construct(array $errors, ?array $request = null)
        {
            $this->message = $this->message ?: esc_html__('Invalid API request', 'cdekdelivery');

            parent::__construct([
                'errors' => $errors,
                'request' => $request,
            ]);
        }
    }
}
