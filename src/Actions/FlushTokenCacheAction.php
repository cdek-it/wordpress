<?php
declare(strict_types=1);

namespace Cdek\Actions;

use Cdek\Token\Token;

class FlushTokenCacheAction
{
    final public function __invoke(): void
    {
        Token::flushCache();
    }
}
