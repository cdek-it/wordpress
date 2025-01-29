<?php

declare(strict_types=1);

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek\Model {
    use Throwable;

    class Log
    {
        private string $message;
        private array $context;
        private ?Throwable $exception;

        public function __construct(string $message, array $context = [], Throwable $exception = null)
        {
            $this->message = $message;
            $this->context = $context;
            $this->exception = $exception;
        }

        /**
         * @param  string  $message
         * @param  array|Throwable  $context
         */
        public static function initLog(string $message, $context = []): self
        {
            if ( $context instanceof Throwable ) {
                return static::initWithException($message, $context);
            }

            return static::initWithContext($message, $context);
        }

        public static function initWithContext(string $message, array $context): Log
        {
            return new static($message, $context);
        }

        public static function initWithException(string $message, Throwable $exception): Log
        {
            return new static($message, [], $exception);
        }

        public function getMessage(): string
        {
            return $this->message;
        }

        public function getLog(): ?array
        {
            if ( $this->isException() ) {
                $e = $this->getException();

                return [
                    'message' => $e->getMessage(),
                    'file'    => "{$e->getFile()}:{$e->getLine()}",
                    'trace'   => $e->getTrace(),
                ];
            }

            return $this->getContext();
        }

        private function isException(): bool
        {
            return $this->exception !== null;
        }

        private function getContext(): array
        {
            return $this->context;
        }

        private function getException(): Throwable
        {
            return $this->exception;
        }
    }
}
