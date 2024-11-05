<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\Actions\IntakeCreateAction;
    use Cdek\Actions\IntakeDeleteAction;
    use Cdek\Config;
    use Cdek\Helpers\DataWPScraber;
    use WP_Http;
    use WP_REST_Request;
    use WP_REST_Response;
    use WP_REST_Server;

    class CourierController
    {
        public static function callCourier(WP_REST_Request $request): WP_REST_Response
        {
            return new WP_REST_Response(
                IntakeCreateAction::new()($request->get_param('id'), DataWPScraber::getData($request, [
                    'date',
                    'starttime',
                    'endtime',
                    'desc',
                    'name',
                    'phone',
                    'address',
                    'comment',
                    'weight',
                    'length',
                    'width',
                    'height',
                    'need_call',
                ]))->response(), WP_Http::OK,
            );
        }

        public static function deleteCourierCall(WP_REST_Request $data): WP_REST_Response
        {
            return new WP_REST_Response(IntakeDeleteAction::new()($data->get_param('id'))->response(), WP_Http::OK);
        }

        public function __invoke(): void
        {
            register_rest_route(Config::DELIVERY_NAME, '/order/(?P<id>\d+)/courier', [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'callCourier'],
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

            register_rest_route(Config::DELIVERY_NAME, '/order/(?P<id>\d+)/courier/delete', [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'deleteCourierCall'],
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
