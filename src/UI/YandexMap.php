<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\UI{

    use Cdek\Helper;

    class YandexMap {
        public function __invoke()
        {
            $cdekShippingSettings = Helper::getSettingDataPlugin();
            if (array_key_exists('yandex_map_api_key',
                    $cdekShippingSettings) && $cdekShippingSettings['yandex_map_api_key'] !== '') {
                $WP_Http = new WP_Http();
                $resp    = $WP_Http->request('https://api-maps.yandex.ru/2.1?apikey='.$cdekShippingSettings['yandex_map_api_key'].'&lang=ru_RU',
                    [
                        'method'  => 'GET',
                        'headers' => [
                            "Content-Type" => "application/json",
                        ],
                    ]);

                if ($resp['response']['code'] === 200) {
                    wp_enqueue_script('cdek-admin-yandex-api',
                        'https://api-maps.yandex.ru/2.1?apikey='.$cdekShippingSettings['yandex_map_api_key'].'&lang=ru_RU');
                    wp_enqueue_script('cdek-admin-leaflet-yandex', plugin_dir_url(__FILE__).'assets/js/lib/Yandex.js');
                } else {
                    $setting = WC()->shipping->load_shipping_methods()['official_cdek'];
                    $setting->update_option('yandex_map_api_key', '');
                    $setting->update_option('map_layer', '1');
                }

            } else {
                $cdekShippingSettings['map_layer'] = '0';
            }
        }
    }
}
