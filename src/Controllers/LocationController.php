<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\CdekApi;
    use Cdek\Config;
    use WP_Error;
    use WP_REST_Request;
    use WP_REST_Response;
    use WP_REST_Server;

    class LocationController
    {
        public static function getPoints(WP_REST_Request $data): WP_REST_Response
        {
            $data = (new CdekApi)->getOffices($data->get_params());

            return new WP_REST_Response($data instanceof WP_Error ? $data : $data['body'], 200, [
                'x-current-page'   => $data['headers']['x-current-page'] ?? null,
                'x-total-pages'    => $data['headers']['x-total-pages'] ?? null,
                'x-total-elements' => $data['headers']['x-total-elements'] ?? null,
            ]);
        }

        public function __invoke(): void
        {
            register_rest_route(Config::DELIVERY_NAME, '/get-offices', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'getPoints'],
                'permission_callback' => static fn() => current_user_can('manage_woocommerce'),
            ]);
        }
    }
}
