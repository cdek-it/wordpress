<?php

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\Controllers {

    use Cdek\CallCourier;
    use Cdek\DataWPScraber;

    class CourierController {
        public static function callCourier($data): string {
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

            return $callCourier->call($param);
        }

        public static function deleteCourierCall($data): string {
            return (new CallCourier())->delete($data->get_param('order_id'));
        }

        public function __invoke() {
            register_rest_route('cdek/v1', '/call-courier', [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'callCourier'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('cdek/v1', '/call-courier-delete', [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'deleteCourierCall'],
                'permission_callback' => '__return_true',
            ]);
        }
    }
}
