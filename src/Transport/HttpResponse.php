<?php

/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */
declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Transport {

    use Cdek\Exceptions\External\UnparsableAnswerException;
    use JsonException;

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
         * @throws UnparsableAnswerException
         */
        public function data(): array
        {
            return $this->json()['data'] ?? [];
        }

        /**
         * @throws UnparsableAnswerException
         */
        public function json(): array
        {
            if (!isset($this->headers['content-type']) ||
                strpos($this->headers['content-type'], 'application/json') === false) {
                throw new UnparsableAnswerException($this->body);
            }

            if ($this->decodedBody === null) {
                try {
                    $this->decodedBody = json_decode(
                        $this->body,
                        true,
                        512,
                        JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
                    );
                } catch (JsonException $e) {
                    throw new UnparsableAnswerException($this->body);
                }
            }

            return $this->decodedBody;
        }

        /**
         * @throws UnparsableAnswerException
         */
        public function nextCursor(): ?string
        {
            return $this->json()['cursor']['next'] ?? null;
        }

        /**
         * @throws UnparsableAnswerException
         */
        public function error(): array
        {
            return $this->json()['error'] ?? [];
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
