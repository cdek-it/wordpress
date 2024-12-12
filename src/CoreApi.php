<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    use Cdek\Exceptions\External\ApiException;
    use Cdek\Exceptions\External\CoreAuthException;
    use Cdek\Exceptions\ShopRegistrationException;
    use Cdek\Helpers\Tokens;
    use Cdek\Model\TaskResult;
    use Cdek\Transport\HttpClient;

    final class CoreApi
    {
        private string $tokenType;

        public function __construct(string $tokenType = 'wordpress')
        {
            $this->tokenType = $tokenType;
        }

        /**
         * @throws CoreAuthException
         * @throws ApiException
         * @throws \Cdek\Exceptions\CacheException
         */
        public function keyringFetch(): array
        {
            return HttpClient::sendJsonRequest(
                $this->getEndpoint('.well-known/key', false),
                'GET',
                null,
            )->data();
        }

        public function hasToken(): bool
        {
            try {
                $this->getToken();
                return true;
            } catch (CoreAuthException $e) {
                return false;
            }
        }

        /**
         * @throws CoreAuthException
         * @throws \Cdek\Exceptions\CacheException
         */
        private function getEndpoint(string $path, bool $strict = true): string
        {
            $base = Tokens::getEndpoint($this->tokenType);

            if ($base === null) {
                if ($strict) {
                    throw new CoreAuthException;
                }

                /** @noinspection GlobalVariableUsageInspection */
                return ($_ENV['CDEK_CORE_API'] ?? Config::API_CORE_URL)."cms/$path";
            }

            return "$base/$path";
        }

        /**
         * @throws ApiException
         * @throws CoreAuthException
         * @throws \Cdek\Exceptions\CacheException
         */
        public function orderGet(int $orderId): array
        {
            return HttpClient::sendJsonRequest(
                $this->getEndpoint("orders/$orderId"),
                'GET',
                $this->getToken(),
            )->data();
        }

        /**
         * @throws CoreAuthException
         * @throws \Cdek\Exceptions\CacheException
         */
        private function getToken(): string
        {
            $token = Tokens::get($this->tokenType);

            if ($token === null) {
                throw new CoreAuthException;
            }

            return $token;
        }

        /**
         * @throws ApiException
         * @throws CoreAuthException
         * @throws \Cdek\Exceptions\CacheException
         */
        public function validatePhone(string $phone, ?string $country = null): string
        {
            return HttpClient::sendJsonRequest(
                $this->getEndpoint('validate/phone'),
                'GET',
                $this->getToken(),
                ['phone' => $phone, 'country' => $country],
            )->data()['phone'];
        }

        /**
         * @throws ShopRegistrationException
         * @throws CoreAuthException
         * @throws \Cdek\Exceptions\CacheException
         */
        public function shopSync(string $authIntToken, string $name, string $rest, string $home, string $admin): string
        {
            try {
                $response = HttpClient::sendJsonRequest(
                    $this->getEndpoint('wordpress/shop', false),
                    'POST',
                    $authIntToken,
                    [
                        'name' => $name,
                        'url'  => [
                            'rest'  => $rest,
                            'home'  => $home,
                            'admin' => $admin,
                        ],
                    ],
                );

                if (empty($response->getHeaders()['x-entity-id'])) {
                    throw new ShopRegistrationException;
                }

                return $response->getHeaders()['x-entity-id'];
            } catch (ApiException $e) {
                throw new ShopRegistrationException;
            }
        }

        /**
         * @throws CoreAuthException
         * @throws \Cdek\Exceptions\CacheException
         */
        public function shopTokensFetch(string $authIntToken, string $shopId): array
        {
            try {
                $response = HttpClient::sendJsonRequest(
                    $this->getEndpoint("shops/$shopId/tokens", false),
                    'POST',
                    $authIntToken,
                );

                return $response->data();
            } catch (ApiException $e) {
                throw new CoreAuthException;
            }
        }

        /**
         * @throws ApiException
         * @throws CoreAuthException
         * @throws \Cdek\Exceptions\CacheException
         */
        public function taskGet(string $taskId): array
        {
            return HttpClient::sendJsonRequest(
                $this->getEndpoint("tasks/$taskId"),
                'GET',
                $this->getToken(),
            )->data();
        }

        /**
         * @throws ApiException
         * @throws CoreAuthException
         * @throws \Cdek\Exceptions\CacheException
         */
        public function taskList(?string $next = null): Transport\HttpResponse
        {
            return HttpClient::sendJsonRequest(
                $this->getEndpoint('tasks'),
                'GET',
                $this->getToken(),
                ($next === null ? null : [
                    'cursor' => $next,
                ]),
            );
        }

        /**
         * @throws ApiException
         * @throws CoreAuthException
         * @throws \Cdek\Exceptions\CacheException
         */
        public function taskResultCreate(string $taskId, TaskResult $data): void
        {
            HttpClient::sendJsonRequest(
                $this->getEndpoint("tasks/$taskId"),
                'PUT',
                $this->getToken(),
                [
                    'status' => $data->getStatus(),
                    'result' => $data->getData(),
                ],
                [
                    'X-Current-Page' => $data->getCurrentPage(),
                    'X-Total-Pages'  => $data->getTotalPages(),
                ],
            );
        }
    }
}
