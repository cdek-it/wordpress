<?php

namespace Cdek;

use Cdek\Model\SettingData;
use Cdek\Model\Tariff;
use WP_Http;

class CdekApi
{
    protected const API = "https://api.cdek.ru/v2/";
    protected const TOKEN_PATH = "oauth/token";
    protected const REGION_PATH = "location/cities";
    protected const ORDERS_PATH = "orders/";
    protected const PVZ_PATH = "deliverypoints";
    protected const CALC_PATH = "calculator/tariff";
    protected const WAYBILL_PATH = "print/orders/";

    protected $settingData;
    protected $httpClient;

    public function __construct(SettingData $settingData)
    {
        $this->settingData = $settingData;
        $this->httpClient = new HttpClientWrapper();
    }

    public function getToken()
    {
        $clientId = $this->settingData->getClientId();
        $clientSecret = $this->settingData->getClientSecret();

	    $body = $this->getResponseBody($clientId, $clientSecret);

	    if (property_exists($body, 'error')) {
		    return false;
	    }

	    return "Bearer " . $body->access_token;
    }

	protected function getResponseBody($clientId, $clientSecret)
	{
		$authUrl  = $this->getAuthUrl($clientId, $clientSecret);
		$response = wp_remote_post($authUrl);
		$bodyJson = wp_remote_retrieve_body($response);

		return json_decode($bodyJson);
	}

	protected function getAuthUrl($clientId, $clientSecret)
	{
		return self::API . self::TOKEN_PATH . "?grant_type=client_credentials&client_id=" . $clientId
			. "&client_secret=" . $clientSecret;
	}

	public function checkAuth($clientId, $clientSecret)
	{
		$body = $this->getResponseBody($clientId, $clientSecret);

		if (property_exists($body, 'error')) {
			$this->setSettingPluginAuthCheck(false);
			return json_encode(['state' => false, 'message' => $body->error_description]);
		}

		$this->setSettingPluginAuthCheck(true);
		return json_encode(['state' => true]);
	}

	protected function setSettingPluginAuthCheck(bool $state)
	{
		$cdekShippingSettings = getSettingDataPlugin();
		$cdekShippingSettings['auth_check'] = (string)(int)$state;
	}

	public function getOrder($number)
	{
		$url = self::API . self::ORDERS_PATH . $number;
		return $this->httpClient->sendRequest($url, 'GET', $this->getToken());
	}

    public function createOrder($param)
    {
        $url = self::API . self::ORDERS_PATH;
//        $param['developer_key'] = $this->settingData->developerKey;

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

	    return $this->httpClient->sendRequest($url, 'POST', $this->getToken(), json_encode($param));
    }

    public function getWaybill($number)
    {
        $url = 'http://api.cdek.ru/v2/' . self::WAYBILL_PATH . $number . '.pdf';
        return $this->httpClient->sendRequest($url, 'GET', $this->getToken());
    }

    public function getWaybillByLink($link)
    {
        $WP_Http = new WP_Http();
        $resp = $WP_Http->request( $link, [
            'method' => 'GET',
            'headers' => [
                "Content-Type" => "application/json",
                "Authorization" => $this->getToken()
            ],
        ] );

        return $resp['body'];
    }

    public function createWaybill($orderUuid)
    {
        $url = self::API . self::WAYBILL_PATH;
        return $this->httpClient->sendRequest($url, 'POST', $this->getToken(), json_encode(['orders' => ['order_uuid' => $orderUuid]]));
    }

    public function deleteOrder($number)
    {
        $url = self::API . self::ORDERS_PATH . $number;
        return $this->httpClient->sendRequest($url, 'DELETE', $this->getToken());
    }

    public function getPvz($city)
    {
        $url = self::API . self::PVZ_PATH;
        if (empty($city)) {
            $city = '44';
        }
        $result = $this->httpClient->sendRequest($url, 'GET', $this->getToken(), ['city_code' => $city]);
        $pvz = array_map(function($elem) { return array_merge(['code' => $elem->code, 'type' => $elem->type], (array)$elem->location);}, json_decode($result));
        return json_encode($pvz);
    }

    public function calculateWP($city, $state, $weight, $length, $width, $height, $tariff, $services)
    {
        $url = self::API . self::CALC_PATH;

        $toLocationCityCode = $this->getCityCodeByCityName($city, $state);

        $token = $this->getToken();
        $result = $this->httpClient->sendRequest($url, 'POST', $token, json_encode([
            'tariff_code' => $tariff,
            'from_location' => [
                'code' => $this->settingData->getFromCity()
            ],
            'to_location' => [
                'code' => $toLocationCityCode,
            ],
            'packages' => [
                'weight' => $weight,
                'length' => $length,
                'width' => $width,
                'height' => $height,
            ],
            'services' => $services
        ]));

        return $result;
    }

    public function getRegion($city = null)
    {
        $url = self::API . self::REGION_PATH;
        return $this->httpClient->sendRequest($url, 'GET', $this->getToken(), ['city' => $city]);
    }

    public function getCityCodeByCityName($city, $state)
    {
        $url = self::API . self::REGION_PATH;
        $cityData = json_decode($this->httpClient->sendRequest($url, 'GET', $this->getToken(), ['city' => $city]));

        if (empty($cityData)) {
            return -1;
        }

        if (count($cityData) > 1) {
            foreach ($cityData as $data) {
                if ($data->region === $state) {
                    return $data->code;
                }
            }
        }
        return $cityData[0]->code;
    }

    public function getCityCode($city, $postalCode)
    {
        $url = self::API . self::REGION_PATH;
        $cityData = json_decode($this->httpClient->sendRequest($url, 'GET', $this->getToken(), ['city' => $city, 'postal_code' => $postalCode]));
        return $cityData[0]->code;
    }

}
