<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Model {

    class ValidationResult
    {
        public bool $state;
        public string $message;

        public function __construct(bool $state, string $message = '')
        {
            $this->state   = $state;
            $this->message = $message;
        }

        final public function response(): array
        {
            return ['state' => $this->state, 'message' => $this->message];
        }

        final public function state(): bool
        {
            return $this->state;
        }
    }
}
