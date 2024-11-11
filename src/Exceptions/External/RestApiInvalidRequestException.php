<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\Exceptions\External {

    use Cdek\Contracts\ExceptionContract;

    /**
     * @deprecated use HttpClientException instead
     */
    class RestApiInvalidRequestException extends ExceptionContract
    {
        protected string $key = 'cdek_error.rest.validation';

        public function __construct(
            string $endpoint,
            array $errors
        ) {
            parent::__construct('Error happened during request to CDEK API validation', [
                'endpoint' => $endpoint,
                'errors'   => $errors,
            ]);
        }
    }
}
