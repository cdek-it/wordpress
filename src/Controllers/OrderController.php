<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\Actions\CreateOrderAction;
    use Cdek\Actions\DeleteOrderAction;
    use Cdek\Config;
    use WP_REST_Request;
    use WP_REST_Response;
    use WP_REST_Server;

    class OrderController
    {
        /**
         * @throws \JsonException
         * @throws \Cdek\Exceptions\RestApiInvalidRequestException
         */
        public static function createOrder(WP_REST_Request $request): WP_REST_Response
        {
            return new WP_REST_Response((new CreateOrderAction)($request->get_param('id'),
                                                                0,
                                                                $request->get_param('packages')), 200);
        }

        public static function deleteOrder(WP_REST_Request $request): WP_REST_Response
        {
            return new WP_REST_Response((new DeleteOrderAction)($request->get_param('id')), 200);
        }

        public function __invoke(): void
        {
            register_rest_route(Config::DELIVERY_NAME, '/order/(?P<id>\d+)/create', [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'createOrder'],
                'permission_callback' => static fn() => current_user_can('edit_posts'),
                'show_in_index'       => true,
                'args'                => [
                    'id'       => [
                        'description' => 'Номер заказа',
                        'required'    => true,
                        'type'        => 'integer',
                    ],
                    'packages' => [
                        'description' => 'Упаковки',
                        'required'    => true,
                        'type'        => 'array',
                        'minItems'    => 1,
                        'maxItems'    => 255,
                        'items'       => [
                            'type'                 => 'object',
                            'additionalProperties' => false,
                            'properties'           => [
                                'length' => [
                                    'description' => 'Длина упаковки',
                                    'required'    => true,
                                    'type'        => 'integer',
                                ],
                                'width'  => [
                                    'description' => 'Ширина упаковки',
                                    'required'    => true,
                                    'type'        => 'integer',
                                ],
                                'height' => [
                                    'description' => 'Высота упаковки',
                                    'required'    => true,
                                    'type'        => 'integer',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            register_rest_route(Config::DELIVERY_NAME, '/order/(?P<id>\d+)/delete', [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'deleteOrder'],
                'permission_callback' => static fn() => current_user_can('edit_posts'),
                'show_in_index'       => true,
                'args'                => [
                    'id' => [
                        'description' => 'Номер заказа',
                        'required'    => true,
                        'type'        => 'integer',
                    ],
                ],
            ]);
        }
    }

}
