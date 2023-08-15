<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\CdekApi;
    use Cdek\Config;
    use WP_REST_Request;
    use WP_REST_Response;
    use WP_REST_Server;

    class LocationController {

        public static function getRegion(WP_REST_Request $data): WP_REST_Response {
            return new WP_REST_Response((new CdekApi)->getRegion($data->get_param('city')), 200);
        }

        public static function getPoints(WP_REST_Request $data): WP_REST_Response {
            return new WP_REST_Response((new CdekApi)->getPvz($data->get_param('city_code'), $data->get_param('weight'),
                $data->get_param('admin')), 200);
        }

        public static function setTmpPointCode(WP_REST_Request $data): WP_REST_Response {
            WC()->session->set('pvz_code', $data->get_param('pvz_code'));

            return new WP_REST_Response('', 204);
        }

        public function __invoke() {
            register_rest_route(Config::DELIVERY_NAME, '/get-region', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'getRegion'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route(Config::DELIVERY_NAME, '/get-pvz', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'getPoints'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route(Config::DELIVERY_NAME, '/set-pvz-code-tmp', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'setTmpPointCode'],
                'permission_callback' => '__return_true',
            ]);
        }
    }
}
