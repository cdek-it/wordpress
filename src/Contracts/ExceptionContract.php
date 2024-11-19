<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Contracts {

    use Cdek\Config;
    use Exception;

    abstract class ExceptionContract extends Exception
    {
        protected string $key = 'cdek_error';
        protected int $status = 500;
        private ?array $data;

        public function __construct(?array $data = null)
        {
            $this->data    = $data ?? [];
            $this->message = '['.Config::PLUGIN_NAME.'] '.($this->message ?: 'Unknown error');

            parent::__construct($this->message);
        }

        final public function getData(): array
        {
            return $this->data;
        }

        final public function getKey(): string
        {
            return $this->key;
        }

        final public function getStatusCode(): int
        {
            return $this->status;
        }
    }
}
