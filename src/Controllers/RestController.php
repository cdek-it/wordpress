<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Enums\BarcodeFormat;
    use Cdek\Helper;
    use WP_REST_Request;

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

        public static function getBarcode(WP_REST_Request $request) {
            $api = new CdekApi;

            $order = json_decode($api->getOrderByCdekNumber($request->get_param('id')), true);

            if (!isset($order['entity'])) {
                echo 'Не удалось создать ШК. 
        Для решения проблемы, попробуй пересоздать заказ. Нажмите кнопку "Отменить"
        и введите габариты упаковки повторно.';
                exit();
            }

            if (isset($order['related_entities'])) {
                foreach ($order['related_entities'] as $entity) {
                    if ($entity['type'] === 'barcode' && isset($entity['url'])) {
                        $barcodeInfo = json_decode($api->getBarcode($entity['uuid']), true);

                        if ($barcodeInfo['entity']['format'] !== BarcodeFormat::getByIndex(Helper::getSettingDataPlugin()['barcode_format'])) {
                            continue;
                        }

                        header('Content-type: application/pdf');
                        echo $api->getFileByLink($entity['url']);
                        exit();
                    }
                }
            }

            $barcode = json_decode($api->createBarcode($order['entity']['uuid']), true);

            if (!isset($barcode['entity'])) {
                echo 'Не удалось создать ШК. 
        Для решения проблемы, попробуй пересоздать заказ. Нажмите кнопку "Отменить"
        и введите габариты упаковки повторно.';
                exit();
            }

            sleep(Config::GRAPHICS_FIRST_SLEEP);

            for ($i = 0; $i < Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS; $i++) {
                $barcodeInfo = json_decode($api->getBarcode($barcode['entity']['uuid']), true);

                if (isset($barcodeInfo['entity']['url'])) {
                    header('Content-type: application/pdf');
                    echo $api->getFileByLink($barcodeInfo['entity']['url']);
                    exit();
                }

                if (!isset($barcodeInfo['entity']) || end($barcodeInfo['entity']['statuses'])['code'] === 'INVALID') {
                    echo 'Не удалось создать ШК. 
        Для решения проблемы, попробуй повторно запросить ШК.';
                    exit();
                }

                sleep(Config::GRAPHICS_TIMEOUT_SEC);
            }

            echo 'Запрос на ШК был отправлен, но ответ по нему не пришел.
        Для решения проблемы, попробуй подождать 1 час и попробуй запросить ШК еще раз.';
            exit();
        }

        public function __invoke() {
            register_rest_route('cdek/v1', '/check-auth', [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'checkAuth'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('cdek/v1', '/get-region', [
                'methods'             => 'GET',
                'callback'            => 'get_region',
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('cdek/v1', '/get-city-code', [
                'methods'             => 'GET',
                'callback'            => 'get_city_code',
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('cdek/v1', '/get-pvz', [
                'methods'             => 'GET',
                'callback'            => 'get_pvz',
                'permission_callback' => '__return_true',
            ]);

                register_rest_route('cdek/v1', '/get-waybill', [
                    'methods'             => 'GET',
                    'callback'            => [__CLASS__, 'getWaybill'],
                    'permission_callback' => '__return_true',
                ]);

                register_rest_route('cdek/v1', '/set-pvz-code-tmp', [
                    'methods'             => 'GET',
                    'callback'            => 'set_pvz_code_tmp',
                    'permission_callback' => '__return_true',
                ]);

                register_rest_route(Config::DELIVERY_NAME, '/order/(?P<id>\d+)/barcode', [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [__CLASS__, 'getBarcode'],
                    'permission_callback' => static fn() => current_user_can('manage_woocommerce'),
                    'show_in_index'       => true,
                    'args'                => [
                        'id' => [
                            'description' => 'CDEK Order ID',
                            'required'    => true,
                            'type'        => 'number',
                        ],
                    ],
                ]);
            }
    }

}
