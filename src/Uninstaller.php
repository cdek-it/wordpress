<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    use Cdek\Traits\CanBeCreated;

    class Uninstaller
    {
        use CanBeCreated;

        public function __invoke(): void {}
    }
}
