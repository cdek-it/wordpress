<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\Helpers\DBTokenStorage;
    use Cdek\Traits\CanBeCreated;

    class FlushTokenCacheAction
    {
        use CanBeCreated;
        final public function __invoke(): void
        {
            DBTokenStorage::flushCache();
        }
    }
}
