<?php

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\Controllers {

    use Cdek\Actions\CallCourier;
    use Cdek\Config;
    use Cdek\Helpers\DataWPScraber;
    use WP_REST_Request;
    use WP_REST_Response;

    class CourierController {
        public static function callCourier(WP_REST_Request $data): WP_REST_Response {
            $callCourier = new CallCourier();
            $param       = DataWPScraber::getData($data, [
                'order_id',
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
            ]);

            return new WP_REST_Response($callCourier->call($param), 200);
        }

        public static function deleteCourierCall(WP_REST_Request $data): WP_REST_Response {
            return new WP_REST_Response((new CallCourier())->delete($data->get_param('order_id')), 200);
        }

        public function __invoke() {
            register_rest_route(Config::DELIVERY_NAME, '/call-courier', [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'callCourier'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route(Config::DELIVERY_NAME, '/call-courier-delete', [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'deleteCourierCall'],
                'permission_callback' => '__return_true',
            ]);
        }
    }
}
