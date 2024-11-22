<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Contracts {

    use Cdek\Loader;
    use Exception;

    abstract class ExceptionContract extends Exception
    {
        protected string $key = 'cdek_error';
        protected int $status = 500;
        private ?array $data;

        public function __construct(?array $data = null)
        {
            $this->data    = $data ?? [];
            $this->message = '['.
                             Loader::getPluginName().
                             '] '.
                             ($this->message ?: esc_html__('Unknown error', 'cdekdelivery'));

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
