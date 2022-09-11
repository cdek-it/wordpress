<?php

namespace Cdek;

use Cdek\Model\SettingData;
use Cdek\Model\Tariff;

class CdekApi
{
    protected const URL = "https://api.cdek.ru/v2/";
    protected const URL_TEST = "https://api.cdek.ru/v2/";
    protected const TOKEN = "/oauth/token";
    protected const REGION = "location/cities";
    protected const ORDERS = "orders/";
    protected const PVZ = "deliverypoints";
    protected const CALC = "calculator/tariff";
    protected const WAYBILL = "print/orders/";

    protected $settingData;
    protected $httpClient;

    public function __construct(SettingData $settingData)
    {
        $this->settingData = $settingData;
        $this->httpClient = new HttpClientWrapper();
    }

    public function getOrder($number)
    {
        $url = $this->getUrl() . self::ORDERS . $number;
        return $this->httpClient->sendCurl($url, 'GET', $this->getToken());
    }

    public function getToken()
    {
        $grantType = $this->settingData->getGrantType();
        $clientId = $this->settingData->getClientId();
        $clientSecret = $this->settingData->getClientSecret();
        $authUrl = $this->getUrl() . self::TOKEN . "?grant_type=" . $grantType . "&client_id=" . $clientId . "&client_secret=" . $clientSecret;
        $curlAuth = curl_init($authUrl);
        curl_setopt($curlAuth, CURLOPT_URL, $authUrl);
        curl_setopt($curlAuth, CURLOPT_RETURNTRANSFER, true);
        $headers = array(
            "Accept: application/json"
        );
        curl_setopt($curlAuth, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curlAuth, CURLOPT_POST, 1);
        $respAuth = json_decode(curl_exec($curlAuth));
        curl_close($curlAuth);
        return "Bearer " . $respAuth->access_token;
    }

    public function createOrder($param)
    {
        $url = $this->getUrl() . self::ORDERS;
        $param['developer_key'] = $this->settingData->developerKey;

        $param['date_invoice'] = date('Y-m-d');
        $param['shipper_name'] = $this->settingData->shipperName;
        $param['shipper_address'] = $this->settingData->shipperAddress;
        $param['seller'] = ['address' => $this->settingData->sellerAddress];

        if (Tariff::getTariffModeByCode($param['tariff_code'])) {
            $param['shipment_point'] = $this->settingData->pvzCode;
        } else {
            $param['from_location'] = [
                'code' => $this->settingData->fromCity,
                'address' => $this->settingData->fromAddress
            ];
        }

        $result = $this->httpClient->sendCurl($url, 'POST', $this->getToken(), json_encode($param));
        return $result;
    }

    public function getWaybill($number)
    {
        $url = $this->getUrl() . self::WAYBILL . $number;
        return $this->httpClient->sendCurl($url . '.pdf', 'GET', $this->getToken());
    }

    public function deleteOrder($number)
    {
        $url = $this->getUrl() . self::ORDERS . $number;
        return $this->httpClient->sendCurl($url, 'DELETE', $this->getToken());
    }

    public function getPvz($city)
    {
        $url = $this->getUrl() . self::PVZ;
        if (empty($city)) {
            $city = '44';
        }
        $result = $this->httpClient->sendCurl($url, 'GET', $this->getToken(), ['city_code' => $city]);
        $pvz = array_map(fn($elem) => array_merge(['code' => $elem->code, 'type' => $elem->type], (array)$elem->location), json_decode($result));
        return json_encode($pvz);
    }

    public function calculateWP($city, $postcode, $weight, $tariff)
    {
        $url = $this->getUrl() . self::CALC;

        $token = $this->getToken();
        $result = $this->httpClient->sendCurl($url, 'POST', $token, json_encode([
            'tariff_code' => $tariff,
            'from_location' => [
                'code' => $this->settingData->getFromCity()
            ],
            'to_location' => [
                'city' => $city,
                'postal_code' => $postcode
            ],
            'packages' => [
                'weight' => $weight
            ]
        ]));

        return $result;
    }

    public function getRegion($city = null)
    {
        $url = $this->getUrl() . self::REGION;
        return $this->httpClient->sendCurl($url, 'GET', $this->getToken(), ['city' => $city]);
    }

    public function getCityCode($city, $postalCode)
    {
        $url = $this->getUrl() . self::REGION;
        $cityData = json_decode($this->httpClient->sendCurl($url, 'GET', $this->getToken(), ['city' => $city, 'postal_code' => $postalCode]));
        return $cityData[0]->code;
    }

    protected function getUrl() {
        return self::URL;
    }
}
