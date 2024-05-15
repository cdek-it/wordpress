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
         * @throws \Throwable
         */
        public static function createOrder(WP_REST_Request $request): WP_REST_Response
        {
            return new WP_REST_Response((new CreateOrderAction)($request->get_param('id'), 0,
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
                        'description' => __('Order number', 'cdekdelivery'),
                        'required'    => true,
                        'type'        => 'integer',
                    ],
                    'packages' => [
                        'description' => __('Packages', 'cdekdelivery'),
                        'required'    => true,
                        'type'        => 'array',
                        'minItems'    => 1,
                        'maxItems'    => 255,
                        'items'       => [
                            'type'                 => 'object',
                            'additionalProperties' => false,
                            'properties'           => [
                                'length' => [
                                    'description' => __('Packing length', 'cdekdelivery'),
                                    'required'    => true,
                                    'type'        => 'integer',
                                ],
                                'width'  => [
                                    'description' => __('Packing width', 'cdekdelivery'),
                                    'required'    => true,
                                    'type'        => 'integer',
                                ],
                                'height' => [
                                    'description' => __('Packing height', 'cdekdelivery'),
                                    'required'    => true,
                                    'type'        => 'integer',
                                ],
                                'items'  => [
                                    'description' => __('Products in packaging', 'cdekdelivery'),
                                    'required'    => false,
                                    'type'        => 'array',
                                    'minItems'    => 1,
                                    'items'       => [
                                        'type'       => 'object',
                                        'properties' => [
                                            'id' => [
                                                'description' => __('Product ID', 'cdekdelivery'),
                                                'required'    => true,
                                                'type'        => 'integer',
                                            ],
                                            'quantity' => [
                                                'description' => __('Quantity of goods in packaging', 'cdekdelivery'),
                                                'required'    => true,
                                                'type'        => 'integer',
                                            ],
                                        ],
                                    ],
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
                        'description' => __('Order number', 'cdekdelivery'),
                        'required'    => true,
                        'type'        => 'integer',
                    ],
                ],
            ]);
        }
    }

}
