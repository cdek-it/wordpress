<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    use Cdek\Enums\BarcodeFormat;
    use Cdek\Exceptions\External\ApiException;
    use Cdek\Exceptions\External\InvalidRequestException;
    use Cdek\Exceptions\External\LegacyAuthException;
    use Cdek\Helpers\LegacyTokenStorage;
    use Cdek\Helpers\Logger;
    use Cdek\Transport\HttpClient;
    use Cdek\Transport\HttpResponse;

    /**
     * @deprecated use CoreApi instead
     */
    final class CdekApi
    {
        private string $apiUrl;

        private ShippingMethod $deliveryMethod;

        private LegacyTokenStorage $tokenStorage;

        /**
         * @param  int|\Cdek\ShippingMethod|null  $method
         *
         * @noinspection MissingParameterTypeDeclarationInspection
         */
        public function __construct($method = null)
        {
            $this->deliveryMethod = is_a($method, ShippingMethod::class) ? $method : ShippingMethod::factory($method);
            $this->tokenStorage   = new LegacyTokenStorage();

            if (!$this->deliveryMethod->test_mode) {
                $this->apiUrl = Config::API_URL;

                return;
            }

            /** @noinspection GlobalVariableUsageInspection */
            $this->apiUrl = $_ENV['CDEK_REST_API'] ?? Config::TEST_API_URL;
        }

        public function authGetError(): ?string
        {
            try {
                $this->fetchToken();

                return null;
            } catch (LegacyAuthException $e) {
                return $e->getData()['code'] ?? $e->getData()['error'] ?? 'unknown';
            }
        }

        /**
         * @throws LegacyAuthException
         */
        public function fetchToken(): string
        {
            /** @noinspection GlobalVariableUsageInspection */
            if (!isset($_ENV['CDEK_REST_API']) && $this->deliveryMethod->test_mode) {
                $clientId     = Config::TEST_CLIENT_ID;
                $clientSecret = Config::TEST_CLIENT_SECRET;
            } else {
                $clientId     = $this->deliveryMethod->client_id;
                $clientSecret = $this->deliveryMethod->client_secret;
            }

            try {
                $body = HttpClient::processRequest(
                    "{$this->apiUrl}oauth/token?".http_build_query([
                        'grant_type'    => 'client_credentials',
                        'client_id'     => $clientId,
                        'client_secret' => $clientSecret,
                    ]),
                    'POST',
                );

                if (!isset($body->json()['access_token'])) {
                    throw new LegacyAuthException(array_merge($body->json(), [
                        'host'   => parse_url($this->apiUrl, PHP_URL_HOST),
                        'client' => $clientId,
                    ]));
                }

                return $body->json()['access_token'];
            } catch (ApiException $e) {
                throw new LegacyAuthException(
                    array_merge($e->getData(), [
                        'host'   => parse_url($this->apiUrl, PHP_URL_HOST),
                        'client' => $clientId,
                    ]),
                );
            }
        }

        /**
         * @throws ApiException
         * @throws LegacyAuthException
         */
        public function calculateGet(array $deliveryParam): ?float
        {
            $request = [
                'type'          => $deliveryParam['type'],
                'from_location' => $deliveryParam['from'],
                'tariff_code'   => $deliveryParam['tariff_code'],
                'to_location'   => $deliveryParam['to'],
                'packages'      => $deliveryParam['packages'],
                'services'      => array_key_exists('services', $deliveryParam) ? $deliveryParam['services'] : [],
            ];

            $resp = HttpClient::sendJsonRequest(
                "{$this->apiUrl}calculator/tariff",
                'POST',
                $this->tokenStorage->getToken(),
                $request,
            )->json();

            if (empty($resp['total_sum'])) {
                return null;
            }

            return (float)$resp['total_sum'];
        }

        /**
         * @throws LegacyAuthException
         * @throws ApiException
         */
        public function calculateList(array $deliveryParam): array
        {
            $request = [
                'type'          => $deliveryParam['type'],
                'from_location' => $deliveryParam['from'],
                'to_location'   => $deliveryParam['to'],
                'packages'      => $deliveryParam['packages'],
            ];

            $result = HttpClient::sendJsonRequest(
                "{$this->apiUrl}calculator/tarifflist",
                'POST',
                $this->tokenStorage->getToken(),
                $request,
            )->json();

            return $result;
        }

        /**
         * @throws LegacyAuthException
         */
        public function cityCodeGet(string $city, ?string $postcode = null): ?string
        {
            //по запросу к api v2 климовск записан как "климовск микрорайон" поэтому добавляем "микрорайон"
            if (mb_strtolower($city) === 'климовск') {
                $city .= ' микрорайон';
            }

            return $this->cityCodeGetWithFallback($city, $postcode) ?: $this->cityCodeGetWithFallback($city);
        }

        /**
         * @throws LegacyAuthException
         */
        private function cityCodeGetWithFallback(
            string $city,
            ?string $postcode = null
        ): ?string {
            try {
                Logger::debug(
                    'Fetching city',
                    [
                        'city'        => $city,
                        'postal_code' => $postcode,
                    ]
                );

                $result = HttpClient::sendJsonRequest(
                    "{$this->apiUrl}location/cities",
                    'GET',
                    $this->tokenStorage->getToken(),
                    [
                        'city'        => $city,
                        'postal_code' => $postcode,
                    ],
                )->json();

                $result = !empty($result[0]['code']) ? (string)$result[0]['code'] : null;

                Logger::debug("Got city with code $result");

                return $result;
            } catch (ApiException $e) {
                return null;
            }
        }

        /**
         * @throws LegacyAuthException
         */
        public function cityGet(string $city, string $postcode, string $country = null): ?array
        {
            //по запросу к api v2 климовск записан как "климовск микрорайон" поэтому добавляем "микрорайон"
            if (mb_strtolower($city) === 'климовск') {
                $city .= ' микрорайон';
            }

            try {
                $result = HttpClient::sendJsonRequest(
                    "{$this->apiUrl}location/cities",
                    'GET',
                    $this->tokenStorage->getToken(),
                    [
                        'city'          => $city,
                        'postal_code'   => $postcode,
                        'country_codes' => $country === null ? null : [$country],
                    ],
                )->json();

                return $result[0] ?: null;
            } catch (ApiException $e) {
                return null;
            }
        }

        /**
         * @throws LegacyAuthException
         * @throws \Cdek\Exceptions\External\ApiException
         */
        public function citySuggest(string $q, string $country): array
        {
            return HttpClient::sendJsonRequest(
                "{$this->apiUrl}location/suggest/cities",
                'GET',
                $this->tokenStorage->getToken(),
                [
                    'name'         => $q,
                    'country_code' => $country,
                ],
            )->json();
        }

        /**
         * @throws ApiException
         * @throws LegacyAuthException
         */
        public function fileGetRaw(string $link): string
        {
            return HttpClient::sendJsonRequest($link, 'GET', $this->tokenStorage->getToken())->body();
        }

        /**
         * @throws LegacyAuthException
         * @throws ApiException
         */
        public function intakeCreate(array $param): HttpResponse
        {
            return HttpClient::sendJsonRequest(
                "{$this->apiUrl}intakes",
                'POST',
                $this->tokenStorage->getToken(),
                $param,
            );
        }

        /**
         * @throws ApiException
         * @throws LegacyAuthException
         */
        public function intakeDelete(string $uuid): void
        {
            HttpClient::sendJsonRequest(
                "{$this->apiUrl}intakes/$uuid",
                'DELETE',
                $this->tokenStorage->getToken(),
            )->json();
        }

        /**
         * @throws ApiException
         * @throws LegacyAuthException
         */
        public function intakeGet(string $uuid): ?array
        {
            return HttpClient::sendJsonRequest(
                "{$this->apiUrl}intakes/$uuid",
                'GET',
                $this->tokenStorage->getToken(),
            )->entity();
        }

        /**
         * @throws ApiException
         * @throws LegacyAuthException
         */
        public function officeGet(string $code): ?array
        {
            $offices = HttpClient::sendJsonRequest(
                "{$this->apiUrl}deliverypoints",
                'GET',
                $this->tokenStorage->getToken(),
                ['code' => $code],
            )->json();

            if (empty($offices)) {
                return null;
            }

            return $offices[0];
        }

        /**
         * @throws ApiException
         * @throws LegacyAuthException
         */
        public function officeListRaw(string $city): string
        {
            return HttpClient::sendJsonRequest(
                "{$this->apiUrl}deliverypoints",
                'GET',
                $this->tokenStorage->getToken(),
                ['city_code' => $city],
            )->body();
        }

        /**
         * @param  array  $params
         *
         * @return array
         * @throws LegacyAuthException
         * @throws ApiException
         * @throws InvalidRequestException
         */
        public function orderCreate(array $params): ?string
        {
            Logger::debug('Creating order', $params);

            $result = HttpClient::sendJsonRequest(
                "{$this->apiUrl}orders/",
                'POST',
                $this->tokenStorage->getToken(),
                $params,
            )->entity()['uuid'] ?? null;

            Logger::debug("Created order with uuid $result");

            return $result;
        }

        /**
         * @throws LegacyAuthException
         * @throws ApiException
         */
        public function orderDelete(string $uuid): array
        {
            return HttpClient::sendJsonRequest(
                "{$this->apiUrl}orders/$uuid",
                'DELETE',
                $this->tokenStorage->getToken(),
            )->json();
        }

        /**
         * @throws ApiException
         * @throws LegacyAuthException
         */
        public function orderGet(string $uuid): HttpResponse
        {
            return HttpClient::sendJsonRequest(
                "{$this->apiUrl}orders/$uuid",
                'GET',
                $this->tokenStorage->getToken(),
            );
        }

        /**
         * @throws ApiException
         * @throws LegacyAuthException
         */
        public function orderGetByNumber(string $cdekNumber): HttpResponse
        {
            return HttpClient::sendJsonRequest(
                "{$this->apiUrl}orders?" . http_build_query(["cdek_number" => $cdekNumber]),
                'GET',
                $this->tokenStorage->getToken(),
            );
        }

        /**
         * @throws ApiException
         * @throws LegacyAuthException
         */
        public function waybillCreate(string $cdekNumber): ?string
        {
            return HttpClient::sendJsonRequest(
                "{$this->apiUrl}print/orders/",
                'POST',
                $this->tokenStorage->getToken(),
                ['orders' => ['cdek_number' => $cdekNumber]],
            )->entity()['uuid'] ?? null;
        }

        /**
         * @throws LegacyAuthException
         * @throws ApiException
         */
        public function barcodeCreate(string $orderUuid): ?string
        {
            return HttpClient::sendJsonRequest(
                "{$this->apiUrl}print/barcodes",
                'POST',
                $this->tokenStorage->getToken(),
                [
                    'orders' => ['order_uuid' => $orderUuid],
                    'format' => BarcodeFormat::getByIndex(
                        (int)$this->deliveryMethod->get_option(
                            'barcode_format',
                            0,
                        ),
                    ),
                ],
            )->entity()['uuid'] ?? null;
        }

        /**
         * @throws LegacyAuthException
         * @throws ApiException
         */
        public function barcodeGet(string $uuid): ?array
        {
            return HttpClient::sendJsonRequest(
                "{$this->apiUrl}print/barcodes/$uuid",
                'GET',
                $this->tokenStorage->getToken(),
            )->entity();
        }

        /**
         * @throws LegacyAuthException
         * @throws ApiException
         */
        public function waybillGet(string $uuid): ?array
        {
            return HttpClient::sendJsonRequest(
                "{$this->apiUrl}print/orders/$uuid",
                'GET',
                $this->tokenStorage->getToken(),
            )->entity();
        }
    }
}
