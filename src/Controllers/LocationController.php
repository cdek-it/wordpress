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

    class LocationController
    {
        public static function getPoints(WP_REST_Request $data): WP_REST_Response
        {
            return new WP_REST_Response((new CdekApi)->getOffices($data->get_params()), 200);
        }

        public function __invoke(): void
        {
            register_rest_route(Config::DELIVERY_NAME, '/get-offices', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'getPoints'],
                'permission_callback' => static fn() => current_user_can('manage_woocommerce'),
            ]);

            register_rest_route(Config::DELIVERY_NAME, '/set-pvz-code-tmp', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'setTmpPointCode'],
                'permission_callback' => '__return_true',
            ]);
        }
    }
}
