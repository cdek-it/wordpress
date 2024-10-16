<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    use Cdek\Exceptions\AuthException;
    use Cdek\Exceptions\CdekApiException;
    use Cdek\Exceptions\CdekClientException;
    use Cdek\Exceptions\CdekServerException;
    use Cdek\Exceptions\ShopRegistrationException;
    use Cdek\Helpers\CoreTokenStorage;
    use Cdek\Model\TaskOutputData;
    use Cdek\Transport\HttpClient;
    use JsonException;

    final class CoreApi
    {
        private string $tokenType;

        public function __construct(string $tokenType = 'common')
        {
            $this->tokenType = $tokenType;
        }

        /**
         * @throws ShopRegistrationException
         * @throws JsonException
         */
        public function syncShop(string $authIntToken): string
        {
            try {
                $response = HttpClient::sendJsonRequest(
                    Config::API_CORE_URL.'cms/wordpress/shop',
                    'POST',
                    $authIntToken,
                    [
                        'name' => get_bloginfo('name'),
                        'url'  => [
                            'rest'  => rest_url(),
                            'home'  => home_url(),
                            'admin' => admin_url(),
                        ],
                    ],
                );
            } catch (CdekApiException $e) {
                throw new ShopRegistrationException('[CDEKDelivery] Register shop failed', 'cdek_error.register.shop');
            }

            if (empty($response->getHeaders()['X-Entity-Id'])) {
                throw new ShopRegistrationException(
                    '[CDEKDelivery] Failed to get shop uuid', 'cdek_error.uuid.auth', $response->data(),
                );
            }

            return $response->getHeaders()['X-Entity-Id'];
        }

        /**
         * @throws AuthException
         * @throws JsonException
         */
        public function fetchShopTokens(string $authIntToken, string $shopId): array
        {
            try {
                $response = HttpClient::sendJsonRequest(
                    Config::API_CORE_URL."cms/shops/$shopId/tokens",
                    'POST',
                    $authIntToken,
                );
            } catch (CdekApiException $e) {
                throw new AuthException(
                    '[CDEKDelivery] Failed to get shop token', 'auth.core',
                );
            }

            return $response->data();
        }

        /**
         * @throws CdekApiException
         * @throws CdekServerException
         * @throws CdekClientException
         * @throws JsonException
         * @throws AuthException
         */
        public function listTasks(?string $next = null): array
        {
            return HttpClient::sendJsonRequest(
                $this->getEndpoint('tasks'),
                'GET',
                $this->getToken(),
                ($next === null ? null : [
                    'cursor' => $next,
                ]),
            )->data();
        }

        /**
         * @throws CdekApiException
         * @throws CdekServerException
         * @throws CdekClientException
         * @throws JsonException
         * @throws AuthException
         */
        public function getOrderById(int $orderId): array
        {
                return HttpClient::sendJsonRequest(
                    $this->getEndpoint("orders/$orderId"),
                    'GET',
                    $this->getToken(),
                )->data();
        }


        /**
         * @throws CdekApiException
         * @throws JsonException
         * @throws CdekClientException
         * @throws AuthException
         * @throws CdekServerException
         */
        public function getHistory(int $orderId): array
        {
            return HttpClient::sendJsonRequest(
                $this->getEndpoint("orders/$orderId/history"),
                'GET',
                $this->getToken(),
            )->data();
        }

        /**
         * @throws JsonException
         * @throws AuthException
         * @throws CdekApiException
         */
        private function getEndpoint(?string $path = null): string
        {
            $base = CoreTokenStorage::getEndpoint($this->tokenType);

            if ($base === null) {
                throw new AuthException;
            }

            return $path !== null ? "$base/$path" : $base;
        }

        /**
         * @throws AuthException
         * @throws CdekApiException
         * @throws JsonException
         */
        private function getToken(): string
        {
            $token = CoreTokenStorage::getToken($this->tokenType);

            if ($token === null) {
                throw new AuthException;
            }

            return $token;
        }

        /**
         * @throws CdekApiException
         * @throws JsonException
         * @throws AuthException
         * @throws CdekServerException
         * @throws CdekClientException
         */
        public function taskInfo(string $taskId, TaskOutputData $data): array
        {
            return HttpClient::sendJsonRequest(
                $this->getEndpoint("tasks/$taskId"),
                'GET',
                $this->getToken(),
                [
                    'status' => $data->getStatus(),
                    'result' => $data->getData(),
                ],
            )->data();
        }

        /**
         * @throws CdekApiException
         * @throws CdekServerException
         * @throws CdekClientException
         * @throws JsonException
         * @throws AuthException
         */
        public function putTaskResult(string $taskId, TaskOutputData $data): array
        {
            return HttpClient::sendJsonRequest(
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
            )->data();
        }
    }
}
