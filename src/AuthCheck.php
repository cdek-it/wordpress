<?php

namespace Cdek;

class AuthCheck
{
    public static function check()
    {
        $cdekShipping = WC()->shipping->load_shipping_methods()['official_cdek'];
        $cdekShippingSettings = $cdekShipping->settings;
        $authUrl = 'https://api.cdek.ru/v2/oauth/token' . "?grant_type=client_credentials&client_id=" . $cdekShippingSettings['client_id'] . "&client_secret=" . $cdekShippingSettings['client_secret'];
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
        if (property_exists($respAuth, 'error')) {
            return false;
        }
        return true;
    }
}