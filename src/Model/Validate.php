<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Model {
    class Validate
    {
        public bool $state;
        public string $message;

        public function __construct(bool $state, string $message = '')
        {
            $this->state = $state;
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
