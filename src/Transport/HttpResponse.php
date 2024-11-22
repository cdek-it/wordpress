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
        private string $method;
        private string $url;
        private int $statusCode;
        private string $body;
        private array $headers;

        private ?array $decodedBody = null;

        public function __construct(int $statusCode, string $body, array $headers, string $url, string $method)
        {
            $this->statusCode = $statusCode;
            $this->body       = $body;
            $this->headers    = $headers;
            $this->url        = $url;
            $this->method     = $method;
        }

        public function body(): string
        {
            return $this->body;
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
                throw new UnparsableAnswerException($this->body, $this->url, $this->method);
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
                    throw new UnparsableAnswerException($this->body, $this->url, $this->method);
                }
            }

            return $this->decodedBody;
        }

        /**
         * @throws \Cdek\Exceptions\External\UnparsableAnswerException
         */
        public function entity(): ?array
        {
            return $this->json()['entity'] ?? null;
        }

        /**
         * @throws UnparsableAnswerException
         */
        public function error(): ?array
        {
            return $this->json()['error'] ?? $this->legacyRequestErrors()[0] ?? null;
        }

        /**
         * @throws UnparsableAnswerException
         */
        public function legacyRequestErrors(): array
        {
            return $this->json()['requests'][0]['errors'] ?? [];
        }

        public function getHeaders(): array
        {
            return $this->headers;
        }

        public function getStatusCode(): int
        {
            return $this->statusCode;
        }

        public function isClientError(): bool
        {
            return $this->statusCode >= 400 && $this->statusCode < 500;
        }

        public function isServerError(): bool
        {
            return $this->statusCode >= 500 && $this->statusCode < 600;
        }

        public function missInvalidLegacyRequest(): bool
        {
            try {
                if (empty($this->json()['requests'][0]['state'])) {
                    return true;
                }

                return $this->json()['requests'][0]['state'] !== 'INVALID';
            } catch (UnparsableAnswerException $e) {
                return true;
            }
        }

        /**
         * @throws UnparsableAnswerException
         */
        public function nextCursor(): ?string
        {
            return $this->json()['cursor']['next'] ?? null;
        }

        /**
         * @throws \Cdek\Exceptions\External\UnparsableAnswerException
         */
        public function related(): array
        {
            return $this->json()['related_entities'] ?? [];
        }
    }
}
