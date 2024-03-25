<?php
declare(strict_types=1);

namespace Cdek\Actions;

use Cdek\Helpers\DBTokenStorage;

class FlushTokenCacheAction
{
    final public function __invoke(): void
    {
        DBTokenStorage::flushCache();
    }
}
