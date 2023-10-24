<?php

namespace Cdek;

use Cdek\Enums\BarcodeFormat;
use Cdek\Transport\HttpClient;
use WC_Shipping_Method;

class CdekApi
{
    private const TOKEN_PATH = 'oauth/token';
    private const REGION_PATH = 'location/cities';
    private const ORDERS_PATH = 'orders/';
    private const PVZ_PATH = 'deliverypoints';
    private const CALC_LIST_PATH = 'calculator/tarifflist';
    private const CALC_PATH = 'calculator/tarifflist';
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
        $token = $this->getToken();

        return (bool)$token;
    }

    final public function getToken(): string
    {
        $body = json_decode(HttpClient::sendRequest($this->getAuthUrl(), 'POST'));

        if ($body === null || property_exists($body, 'error')) {
            return false;
        }

        return sprintf('Bearer %s', $body->access_token);
    }

    final private function getAuthUrl(): string
    {
        return sprintf('%s%s?%s',
                       $this->apiUrl, self::TOKEN_PATH,
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

    public function getOrderByCdekNumber($number)
    {
        $url = $this->apiUrl . self::ORDERS_PATH;

        return HttpClient::sendCdekRequest($url, 'GET', $this->getToken(), ['cdek_number' => $number]);
    }

    public function createOrder(array $params)
    {
        $url = $this->apiUrl . self::ORDERS_PATH;
        $params['developer_key'] = Config::DEV_KEY;

        return HttpClient::sendCdekRequest($url, 'POST', $this->getToken(), $params);
    }

    final public function getOrderStatus(string $orderUuid)
    {
        $order = HttpClient::sendCdekRequest($this->apiUrl . self::ORDERS_PATH . $orderUuid, 'GET', $this->getToken());
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

    public function calculateTariffList($deliveryParam) {
        $url = $this->apiUrl.self::CALC_LIST_PATH;

        $request = [
            'type'          => $deliveryParam['type'],
            'from_location' => $deliveryParam['from'],
            'to_location'   => [
                'address' => $deliveryParam['address'],
            ],
            'packages'      => [
                'weight' => $deliveryParam['package_data']['weight'],
                'length' => $deliveryParam['package_data']['length'],
                'width'  => $deliveryParam['package_data']['width'],
                'height' => $deliveryParam['package_data']['height'],
            ],
        ];

        return HttpClient::sendCdekRequest($url, 'POST', $this->getToken(), $request);
    }

    public function calculateTariff($deliveryParam) {
        $url = $this->apiUrl.self::CALC_PATH;

        $request = [
            'type'          => $deliveryParam['type'],
            'from_location' => $deliveryParam['from'],
            'tariff_code'   => $deliveryParam['tariff_code'],
            'to_location'   => [
                'address' => $deliveryParam['address'],
            ],
            'packages'      => [
                'weight' => $deliveryParam['package_data']['weight'],
                'length' => $deliveryParam['package_data']['length'],
                'width'  => $deliveryParam['package_data']['width'],
                'height' => $deliveryParam['package_data']['height'],
            ],
            'services'      => array_key_exists('selected_services',
                                                $deliveryParam) ? $deliveryParam['selected_services'] : [],
        ];

        return HttpClient::sendCdekRequest($url, 'POST', $this->getToken(), $request);
    }

    public function getRegion($city = null)
    {
        $url = $this->apiUrl . self::REGION_PATH;

        return json_decode(HttpClient::sendCdekRequest($url, 'GET', $this->getToken(), ['city' => $city]), true);
    }

    public function getCityCodeByCityName(string $city, string $state): int
    {
        $url = $this->apiUrl . self::REGION_PATH;

        //по запросу к api v2 климовск записан как "климовск микрорайон" поэтому добавляем "микрорайон"
        if (mb_strtolower($city) === 'климовск') {
            $city = $city . ' микрорайон';
        }

        $cityData = json_decode(HttpClient::sendCdekRequest($url, 'GET', $this->getToken(), ['city' => $city]));

        if ($state === 'false') {
            return $cityData[0]->code;
        }

        if (empty($cityData)) {
            return -1;
        }

        if (count($cityData) > 1) {
            foreach ($cityData as $data) {
                if ($this->getFormatState($data->region) === $this->getFormatState($state)) {
                    return $data->code;
                }
                if ($this->getFormatState($data->region) === $this->getFormatState($city)) {
                    return $data->code;
                }
            }
        }

        return $cityData[0]->code;
    }

    protected function getFormatState($state)
    {
        $state = mb_strtolower($state);
        $regionType = [
            'автономная область',
            'область',
            'республика',
            'респ.',
            'автономный округ',
            'округ',
            'край',
            'обл.',
            'обл',
        ];
        foreach ($regionType as $type) {
            $state = str_replace($type, '', $state);
        }

        return trim($state);
    }

    public function getCityByCode($code): array
    {
        $url = $this->apiUrl . self::REGION_PATH;
        $cityData = json_decode(HttpClient::sendCdekRequest($url, 'GET', $this->getToken(), ['code' => $code]));

        return ['city' => $cityData[0]->city, 'region' => $cityData[0]->region];
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
