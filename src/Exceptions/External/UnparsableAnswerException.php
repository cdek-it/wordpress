<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\Exceptions\External {

    class UnparsableAnswerException extends HttpServerException
    {
        protected string $key = 'api.parse';

        public function __construct(string $answer)
        {
            parent::__construct(['answer' => $answer]);
        }
    }
}
