<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\CreateOrder;
    use Cdek\DeleteOrder;
    use WP_REST_Response;

    class OrderController {
        public static function createOrder($data): WP_REST_Response {
            return new WP_REST_Response((new CreateOrder)->createOrder($data), 200);
        }

        public static function deleteOrder($data): WP_REST_Response {
            return new WP_REST_Response((new DeleteOrder())->delete($data->get_param('order_id'),
                $data->get_param('number')), 200);
        }

        public function __invoke() {
            register_rest_route('cdek/v1', '/create-order', [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'createOrder'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('cdek/v1', '/delete-order', [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'deleteOrder'],
                'permission_callback' => '__return_true',
            ]);
        }
    }

}
