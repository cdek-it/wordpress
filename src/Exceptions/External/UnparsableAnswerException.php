<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\Exceptions\External {

    class UnparsableAnswerException extends HttpServerException
    {
        protected string $key = 'api.parse';

        public function __construct(string $answer, string $url, string $method)
        {
            $this->message = $this->message ?: esc_html__('Unable to parse API answer', 'cdekdelivery');

            parent::__construct(['answer' => $answer, 'url' => $url, 'method' => $method]);
        }
    }
}
