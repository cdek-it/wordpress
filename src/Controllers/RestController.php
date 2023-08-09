<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\CdekApi;

    class RestController {
        public static function checkAuth(): string {
            $api   = new CdekApi;
            $check = $api->checkAuth();
            if ($check) {
                update_option('cdek_auth_check', '1');
            } else {
                update_option('cdek_auth_check', '0');
            }

            return json_encode(['state' => $check]);
        }

        public static function getWaybill($data): void {
            $api         = new CdekApi;
            $waybillData = $api->createWaybill($data->get_param('number'));
            $waybill     = json_decode($waybillData);

            $order = json_decode($api->getOrder($data->get_param('number')));

            if ($waybill->requests[0]->state === 'INVALID' || property_exists($waybill->requests[0],
                    'errors') || !property_exists($order, 'related_entities')) {
                echo '
        Не удалось создать квитанцию. 
        Для решения проблемы, попробуй пересоздать заказ. Нажмите кнопку "Отменить"
        и введите габариты упаковки повторно.';
                exit();
            }

            foreach ($order->related_entities as $entity) {
                if ($entity->uuid === $waybill->entity->uuid) {
                    $result = $api->getWaybillByLink($entity->url);
                    header("Content-type:application/pdf");
                    echo $result;
                    exit();
                }
            }

            $result = $api->getWaybillByLink(end($order->related_entities)->url);
            header("Content-type:application/pdf");
            echo $result;
            exit();
        }

        public function __invoke() {
            register_rest_route('cdek/v1', '/check-auth', [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'checkAuth'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('cdek/v1', '/get-waybill', [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'getWaybill'],
                'permission_callback' => '__return_true',
            ]);
        }
    }

}
