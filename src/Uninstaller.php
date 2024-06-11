<?php

namespace Cdek;

use Cdek\Cache\FileCache;

class Uninstaller
{
    public function __invoke()
    {
        FileCache::clear();
    }
}
