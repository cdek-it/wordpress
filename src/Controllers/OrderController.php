<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\Actions\CreateOrder;
    use Cdek\Actions\DeleteOrder;
    use Cdek\Config;
    use WP_REST_Request;
    use WP_REST_Response;
    use WP_REST_Server;

    class OrderController {
        public static function createOrder(WP_REST_Request $data): WP_REST_Response {
            return new WP_REST_Response((new CreateOrder)->createOrder($data), 200);
        }

        public static function deleteOrder(WP_REST_Request $data): WP_REST_Response {
            return new WP_REST_Response((new DeleteOrder())->delete($data->get_param('order_id'),
                $data->get_param('number')), 200);
        }

        public function __invoke() {
            register_rest_route(Config::DELIVERY_NAME, '/create-order', [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'createOrder'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route(Config::DELIVERY_NAME, '/delete-order', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'deleteOrder'],
                'permission_callback' => '__return_true',
            ]);
        }
    }

}
