<?php
namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {
    class RestController {
            public function __invoke(){
                register_rest_route('cdek/v1', '/check-auth', [
                    'methods'             => 'GET',
                    'callback'            => 'check_auth',
                    'permission_callback' => '__return_true',
                ]);

                register_rest_route('cdek/v1', '/get-region', [
                    'methods'             => 'GET',
                    'callback'            => 'get_region',
                    'permission_callback' => '__return_true',
                ]);

                register_rest_route('cdek/v1', '/get-city-code', [
                    'methods'             => 'GET',
                    'callback'            => 'get_city_code',
                    'permission_callback' => '__return_true',
                ]);

                register_rest_route('cdek/v1', '/get-pvz', [
                    'methods'             => 'GET',
                    'callback'            => 'get_pvz',
                    'permission_callback' => '__return_true',
                ]);

                register_rest_route('cdek/v1', '/get-waybill', [
                    'methods'             => 'GET',
                    'callback'            => 'get_waybill',
                    'permission_callback' => '__return_true',
                ]);

                register_rest_route('cdek/v1', '/set-pvz-code-tmp', [
                    'methods'             => 'GET',
                    'callback'            => 'set_pvz_code_tmp',
                    'permission_callback' => '__return_true',
                ]);
            }
    }

}
