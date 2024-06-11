<?php

namespace Cdek\Exceptions;
class CdekScheduledTaskException extends \Cdek\Exceptions\CdekException
{
    public function __construct(
        string $message = '',
        string $code = 'cdek_error',
        ?array $data = null
    )
    {
        parent::__construct($message, $code, $data, true);
    }
}
