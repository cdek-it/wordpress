<?php

namespace Cdek\Model;

use Cdek\Helper;

class AdminSetting
{
    public $grantType = 'client_credentials';
    public $clientId;
    public $clientSecret;
    public $tariffList;
    public $sellerName;
    public $sellerPhone;
    public $fromCity;
    public $fromAddress;
    public $pvzCode;
    public $pvzAddress;
    public $developerKey = '7wV8tk&r6VH4zK:1&0uDpjOkvM~qngLl';
    public $shipperName;
    public $shipperAddress;
    public $sellerAddress;
    public $extraDay;
    public $extraCost;
    public $insurance;

    public function getCurrentSetting($currentAdminSetting = null): AdminSetting
    {
        $adminSetting = new AdminSetting();

        if ($currentAdminSetting) {
            $cdekShippingSettings = $currentAdminSetting;
        } else {
            $cdekShippingSettings = Helper::getSettingDataPlugin();
        }

        $adminSetting->clientId = $cdekShippingSettings['client_id'] ?? '';
        $adminSetting->clientSecret = $cdekShippingSettings['client_secret'] ?? '';
        $adminSetting->tariffList = $cdekShippingSettings['tariff_list'] ?? '';
        $adminSetting->sellerName = $cdekShippingSettings['seller_name'] ?? '';
        $adminSetting->sellerPhone = $cdekShippingSettings['seller_phone'] ?? '';
        $adminSetting->fromCity = $cdekShippingSettings['city_code_value'] ?? '';
        $adminSetting->fromAddress = $cdekShippingSettings['address'] ?? '';
        $adminSetting->pvzCode = $cdekShippingSettings['pvz_code'] ?? '';
        $adminSetting->pvzAddress = $cdekShippingSettings['pvz_address'] ?? '';
        $adminSetting->shipperName = $cdekShippingSettings['shipper_name'] ?? '';
        $adminSetting->shipperAddress = $cdekShippingSettings['shipper_address'] ?? '';
        $adminSetting->sellerAddress = $cdekShippingSettings['seller_address'] ?? '';
        $adminSetting->extraDay = $cdekShippingSettings['extra_day'] ?? '';
        $adminSetting->extraCost = $cdekShippingSettings['extra_cost'] ?? '';
        $adminSetting->insurance = $cdekShippingSettings['insurance'] ?? '';
        return $adminSetting;
    }
}
