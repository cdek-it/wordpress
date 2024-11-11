<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions {

    use Cdek\Contracts\ExceptionContract;

    class ScheduledTaskException extends ExceptionContract {
        protected string $key = 'scheduled.task';
    }
}
