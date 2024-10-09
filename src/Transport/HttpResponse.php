<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Transport {

    use RuntimeException;

    final class HttpResponse
    {
        private int $statusCode;
        private string $body;
        private array $headers;

        private ?array $decodedBody = null;

        public function __construct(int $statusCode, string $body, array $headers)
        {
            $this->statusCode = $statusCode;
            $this->body       = $body;
            $this->headers    = $headers;
        }

        public function isSuccessful(): bool
        {
            return $this->statusCode >= 200 && $this->statusCode < 300;
        }

        public function isServerError(): bool
        {
            return $this->statusCode >= 500 && $this->statusCode < 600;
        }

        public function isClientError(): bool
        {
            return $this->statusCode >= 400 && $this->statusCode < 500;
        }

        public function getStatusCode(): int
        {
            return $this->statusCode;
        }

        /**
         * @throws \JsonException
         */
        public function data(): array
        {
            if (!isset($this->headers['content-type']) || strpos($this->headers['content-type'], 'application/json') === false) {
                throw new RuntimeException('Response is not JSON');
            }

            if($this->decodedBody === null){
                $this->decodedBody = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
            }

            return $this->decodedBody['data'] ?? [];
        }

        /**
         * @throws \JsonException
         */
        public function error(): array
        {
            if (!isset($this->headers['content-type']) || strpos($this->headers['content-type'], 'application/json') === false) {
                throw new RuntimeException('Response is not JSON');
            }

            if($this->decodedBody === null){
                $this->decodedBody = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
            }

            return $this->decodedBody['error'] ?? [];
        }

        /**
         * @throws \JsonException
         */
        public function json(): array
        {
            if (!isset($this->headers['content-type']) || strpos($this->headers['content-type'], 'application/json') === false) {
                throw new RuntimeException('Response is not JSON');
            }

            if($this->decodedBody === null){
                $this->decodedBody = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
            }

            return $this->decodedBody;
        }

        public function body(): string
        {
            return $this->body;
        }

        public function getHeaders(): array
        {
            return $this->headers;
        }
    }
}
