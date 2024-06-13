<?php

namespace Cdek;

use Cdek\Actions\FlushTokenCacheAction;

class Uninstaller
{
    public function __invoke()
    {
        (new FlushTokenCacheAction)();
    }
}
