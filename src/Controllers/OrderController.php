<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\CreateOrder;
    use Cdek\DeleteOrder;

    class OrderController {
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

        public static function createOrder($data): string {
            return (new CreateOrder)->createOrder($data);
        }

        public static function deleteOrder($data): string {
            return (new DeleteOrder())->delete($data->get_param('order_id'), $data->get_param('number'));
        }
    }

}
