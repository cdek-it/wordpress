<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\UI {

    use Cdek\Helper;
    use WP_Http;

    class YandexMap {
        public function __invoke() {
            if (!$apiKey = Helper::getActualShippingMethod()->get_option('yandex_map_api_key')) {
                return;
            }

            $resp    = (new WP_Http())->request("https://api-maps.yandex.ru/2.1?apikey=$apiKey&lang=ru_RU", [
                    'method'  => 'GET',
                    'headers' => [
                        "Content-Type" => 'application/json',
                    ],
                ]);

            if ($resp['response']['code'] === 200) {
                wp_enqueue_script('cdek-admin-yandex-api', "https://api-maps.yandex.ru/2.1?apikey=$apiKey&lang=ru_RU");
                wp_enqueue_script('cdek-admin-leaflet-yandex', plugin_dir_url(__FILE__).'assets/js/lib/Yandex.js');
            } else {
                $setting = Helper::getActualShippingMethod();
                $setting->update_option('yandex_map_api_key', '');
                $setting->update_option('map_layer', '1');
            }
        }
    }
}
