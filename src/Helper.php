<?php

namespace Cdek;

use WC_Shipping_Method;

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
        return self::getActualShippingMethod()->get_option('tariff_plug', 'CDEK');
    }

    public static function getActualShippingMethod(): WC_Shipping_Method {
        if (isset(WC()->cart)) {
            $methods = wc_get_shipping_zone(WC()->cart->get_shipping_packages()[0])->get_shipping_methods(true);

            foreach ($methods as $method) {
                if ($method instanceof CdekShippingMethod) {
                    return $method;
                }
            }
        }

        return WC()->shipping->load_shipping_methods()['official_cdek'];
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
