<?php

namespace Cdek;

use Cdek\Cache\FileCache;
use Cdek\Managers\TaskManager;

class Uninstaller
{
    public function __invoke()
    {
        (new FileCache())->clear();
    }
}
