<?php

namespace Cdek;

use WP_Http;

class Helper {
    public static function generateRandomString($length = 10): string {
        $characters       = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString     = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    public static function getTariffPlugName() {
        $setting    = self::getSettingDataPlugin();
        $tariffPlug = "CDEK";
        if (array_key_exists('tariff_plug', $setting) && $setting['tariff_plug'] !== "") {
            $tariffPlug = $setting['tariff_plug'];
        }

        return $tariffPlug;
    }

    public static function getSettingDataPlugin() {
        $cdekShipping = WC()->shipping->load_shipping_methods()['official_cdek'];

        return $cdekShipping->settings;
    }

    public static function buildRestUrl(
        string $route,
        array $args = [],
        string $prefix = Config::DELIVERY_NAME
    ): string {
        $prefix = substr($prefix, -1) === '/' ? $prefix : "$prefix/";

        $args['_wpnonce'] = wp_create_nonce( 'wp_rest' );

        return add_query_arg($args, rest_url($prefix.$route));
    }
}
