<?php

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek {

    use Cdek\Actions\FlushTokenCacheAction;

    class Uninstaller
    {
        public function __invoke()
        {
            (new FlushTokenCacheAction)();
        }
    }
}
