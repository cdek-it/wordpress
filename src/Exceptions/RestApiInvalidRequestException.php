<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\Exceptions {

    class RestApiInvalidRequestException extends CdekException
    {
        public function __construct(
            string $endpoint,
            array $errors
        ) {
            parent::__construct('Error happened during request to CDEK API validation', 'cdek_error.rest.validation', [
                'endpoint' => $endpoint,
                'errors'   => $errors,
            ]);
        }
    }
}
