<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\CdekApi;

    class LocationController {

        public function __invoke(){
            register_rest_route('cdek/v1', '/get-region', [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'getRegion'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('cdek/v1', '/get-pvz', [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'getPoints'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('cdek/v1', '/set-pvz-code-tmp', [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'setTmpPointCode'],
                'permission_callback' => '__return_true',
            ]);
        }

        public static function getRegion($data) {
            return (new CdekApi())->getRegion($data->get_param('city'));
        }

        public static function getPoints($data): string {
            return json_encode((new CdekApi())->getPvz($data->get_param('city_code'), $data->get_param('weight'),
                $data->get_param('admin')));
        }

        public static function setTmpPointCode($data) {
            WC()->session->set('pvz_code', $data->get_param('pvz_code'));
        }
    }
}
