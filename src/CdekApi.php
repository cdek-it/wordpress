<?php

namespace Cdek;

use Cdek\Enums\BarcodeFormat;
use Cdek\Exceptions\CdekApiException;
use Cdek\Token\Token;
use Cdek\Exceptions\RestApiInvalidRequestException;
use Cdek\Transport\HttpClient;
use WC_Shipping_Method;

class CdekApi
{
    private const TOKEN_PATH = 'oauth/token';
    private const REGION_PATH = 'location/cities';
    private const ORDERS_PATH = 'orders/';
    private const PVZ_PATH = 'deliverypoints';
    private const CALC_LIST_PATH = 'calculator/tarifflist';
    private const CALC_PATH = 'calculator/tariff';
    private const WAYBILL_PATH = 'print/orders/';
    private const BARCODE_PATH = 'print/barcodes/';
    private const CALL_COURIER = 'intakes';

    private string $apiUrl;

    private string $clientId;
    private string $clientSecret;
    private WC_Shipping_Method $deliveryMethod;


    public function __construct(?int $shippingInstanceId = null)
    {
        $this->deliveryMethod = Helper::getActualShippingMethod($shippingInstanceId);
        $this->apiUrl = $this->getApiUrl();

        if (!isset($_ENV['CDEK_REST_API']) && $this->deliveryMethod->get_option('test_mode') === 'yes') {
            $this->clientId = Config::TEST_CLIENT_ID;
            $this->clientSecret = Config::TEST_CLIENT_SECRET;
        } else {
            $this->clientId = $this->deliveryMethod->get_option('client_id');
            $this->clientSecret = $this->deliveryMethod->get_option('client_secret');
        }
    }

    private function getApiUrl(): string
    {
        if ($this->deliveryMethod->get_option('test_mode') === 'yes') {
            return $_ENV['CDEK_REST_API'] ?? Config::TEST_API_URL;
        }

        return Config::API_URL;
    }

    final public function checkAuth(): bool
    {
        return (bool)$this->getToken();
    }

    public function getToken(): string
    {
        return (new Token)->getToken();
    }

    /**
     * @throws \Cdek\Exceptions\CdekApiException
     */
    public function fetchToken(): string
    {
        $body = json_decode(HttpClient::sendRequest($this->getAuthUrl(), 'POST'), true);
        if ($body === null || isset($body['error_description']) || isset($body['error'])) {
            throw new CdekApiException('[CDEKDelivery] Failed to get the token',
                                       'cdek_error.token.auth',
                                       $body,
                                       true);
        }
        return $body['access_token'];
    }

    private function getAuthUrl(): string
    {
        return sprintf('%s%s?%s',
                       $this->apiUrl,
                       self::TOKEN_PATH,
                       http_build_query([
                                            'grant_type'    => 'client_credentials',
                                            'client_id'     => $this->clientId,
                                            'client_secret' => $this->clientSecret,
                                        ]));
    }

    final public function getOrder(string $uuid)
    {
        $url = $this->apiUrl . self::ORDERS_PATH . $uuid;

        return HttpClient::sendCdekRequest($url, 'GET', $this->getToken());
    }

    /**
     * @throws \Cdek\Exceptions\RestApiInvalidRequestException
     */
    public function createOrder(array $params)
    {
        $url = $this->apiUrl . self::ORDERS_PATH;
        $params['developer_key'] = Config::DEV_KEY;

        $result = json_decode(HttpClient::sendCdekRequest($url, 'POST', $this->getToken(), $params), true);

        $request = $result['requests'][0];

        if ($request['state'] === 'INVALID') {
            throw new RestApiInvalidRequestException(self::ORDERS_PATH, $request['errors']);
        }

        return $result;
    }

    public function getFileByLink($link)
    {
        return HttpClient::sendCdekRequest($link, 'GET', $this->getToken(), null, true);
    }

    public function createWaybill($orderUuid)
    {
        $url = $this->apiUrl . self::WAYBILL_PATH;

        return HttpClient::sendCdekRequest($url, 'POST', $this->getToken(), ['orders' => ['order_uuid' => $orderUuid]]);
    }

    public function createBarcode($orderUuid)
    {
        return HttpClient::sendCdekRequest($this->apiUrl . self::BARCODE_PATH, 'POST', $this->getToken(), [
            'orders' => ['order_uuid' => $orderUuid],
            'format' => BarcodeFormat::getByIndex($this->deliveryMethod->get_option('barcode_format', 0)),
        ]);
    }

    public function getBarcode($uuid)
    {
        return HttpClient::sendCdekRequest($this->apiUrl . self::BARCODE_PATH . $uuid, 'GET', $this->getToken());
    }

    public function getWaybill($uuid)
    {
        return HttpClient::sendCdekRequest($this->apiUrl . self::WAYBILL_PATH . $uuid, 'GET', $this->getToken());
    }

    public function deleteOrder($uuid)
    {
        $url = $this->apiUrl . self::ORDERS_PATH . $uuid;

        return HttpClient::sendCdekRequest($url, 'DELETE', $this->getToken());
    }

    public function calculateTariffList($deliveryParam)
    {
        $url = $this->apiUrl . self::CALC_LIST_PATH;

        $request = [
            'type'          => $deliveryParam['type'],
            'from_location' => $deliveryParam['from'],
            'to_location'   => $deliveryParam['to'],
            'packages'      => $deliveryParam['packages'],
        ];

        return HttpClient::sendCdekRequest($url, 'POST', $this->getToken(), $request);
    }

    public function calculateTariff($deliveryParam)
    {
        $url = $this->apiUrl . self::CALC_PATH;

        $request = [
            'type'          => $deliveryParam['type'],
            'from_location' => $deliveryParam['from'],
            'tariff_code'   => $deliveryParam['tariff_code'],
            'to_location'   => $deliveryParam['to'],
            'packages'      => $deliveryParam['packages'],
            'services'      => array_key_exists('selected_services',
                                                $deliveryParam) ? $deliveryParam['selected_services'] : [],
        ];

        return HttpClient::sendCdekRequest($url, 'POST', $this->getToken(), $request);
    }

    public function getCityCode(string $city, ?string $postcode): int
    {
        $url = $this->apiUrl . self::REGION_PATH;

        //по запросу к api v2 климовск записан как "климовск микрорайон" поэтому добавляем "микрорайон"
        if (mb_strtolower($city) === 'климовск') {
            $city = $city . ' микрорайон';
        }

        $cityData = json_decode(HttpClient::sendCdekRequest($url, 'GET', $this->getToken(), ['city' => $city, 'postal_code' => $postcode]));

        if (empty($cityData)) {
            return -1;
        }

        return $cityData[0]->code;
    }

    public function getOffices($filter)
    {
        $url = $this->apiUrl . self::PVZ_PATH;

        $result = HttpClient::sendCdekRequest($url, 'GET', $this->getToken(), $filter, true);
        if (!$result) {
            return [
                'success' => false,
                'message' => __(Messages::NO_DELIVERY_POINTS_IN_CITY, Config::TRANSLATION_DOMAIN),
            ];
        }

        return $result;
    }

    public function callCourier($param)
    {
        return HttpClient::sendCdekRequest($this->apiUrl . self::CALL_COURIER, 'POST', $this->getToken(), $param);
    }

    public function courierInfo($uuid)
    {
        return HttpClient::sendCdekRequest($this->apiUrl . self::CALL_COURIER . '/' . $uuid, 'GET', $this->getToken());
    }

    public function callCourierDelete($uuid)
    {
        return HttpClient::sendCdekRequest($this->apiUrl . self::CALL_COURIER . '/' . $uuid,
                                           'DELETE',
                                           $this->getToken());
    }
}
