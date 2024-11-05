<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\Actions\OrderCreateAction;
    use Cdek\Actions\OrderDeleteAction;
    use Cdek\Config;
    use WP_Http;
    use WP_REST_Request;
    use WP_REST_Response;
    use WP_REST_Server;

    class OrderController
    {
        /**
         * @throws \JsonException
         * @throws \Cdek\Exceptions\External\RestApiInvalidRequestException
         * @throws \Throwable
         */
        public static function createOrder(WP_REST_Request $request): WP_REST_Response
        {
            return new WP_REST_Response(
                OrderCreateAction::new()(
                    $request->get_param('id'),
                    0,
                    $request->get_param('packages'),
                ), WP_Http::OK,
            );
        }

        public static function deleteOrder(WP_REST_Request $request): WP_REST_Response
        {
            return new WP_REST_Response(OrderDeleteAction::new()($request->get_param('id'))->response(), WP_Http::OK);
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
                        'description' => esc_html__('Order number', 'cdekdelivery'),
                        'required'    => true,
                        'type'        => 'integer',
                    ],
                    'packages' => [
                        'description' => esc_html__('Packages', 'cdekdelivery'),
                        'required'    => true,
                        'type'        => 'array',
                        'minItems'    => 1,
                        'maxItems'    => 255,
                        'items'       => [
                            'type'                 => 'object',
                            'additionalProperties' => false,
                            'properties'           => [
                                'length' => [
                                    'description' => esc_html__('Packing length', 'cdekdelivery'),
                                    'required'    => true,
                                    'type'        => 'integer',
                                ],
                                'width'  => [
                                    'description' => esc_html__('Packing width', 'cdekdelivery'),
                                    'required'    => true,
                                    'type'        => 'integer',
                                ],
                                'height' => [
                                    'description' => esc_html__('Packing height', 'cdekdelivery'),
                                    'required'    => true,
                                    'type'        => 'integer',
                                ],
                                'items'  => [
                                    'description' => esc_html__('Products in packaging', 'cdekdelivery'),
                                    'required'    => false,
                                    'type'        => 'array',
                                    'minItems'    => 1,
                                    'items'       => [
                                        'type'       => 'object',
                                        'properties' => [
                                            'id'       => [
                                                'description' => esc_html__('Product ID', 'cdekdelivery'),
                                                'required'    => true,
                                                'type'        => 'integer',
                                            ],
                                            'quantity' => [
                                                'description' => esc_html__(
                                                    'Quantity of goods in packaging',
                                                    'cdekdelivery',
                                                ),
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
                        'description' => esc_html__('Order number', 'cdekdelivery'),
                        'required'    => true,
                        'type'        => 'integer',
                    ],
                ],
            ]);
        }
    }
}
