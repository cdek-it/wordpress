<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Traits {

    trait CanBeCreated
    {
        final public static function new(...$args): self
        {
            return new self(...$args);
        }
    }
}
