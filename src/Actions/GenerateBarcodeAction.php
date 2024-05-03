<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Enums\BarcodeFormat;
    use Cdek\Helper;
    use Cdek\Model\OrderMetaData;

    class GenerateBarcodeAction
    {
        private CdekApi $api;

        public function __construct()
        {
            $this->api = new CdekApi;
        }

        public function __invoke(string $cdekUuid): void
        {
            ini_set('max_execution_time',
                    30 +
                    Config::GRAPHICS_FIRST_SLEEP +
                    Config::GRAPHICS_TIMEOUT_SEC * Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS);

            $order = json_decode($this->api->getOrder($cdekUuid), true);

            if (!isset($order['entity'])) {
                echo 'Не удалось получить сведения о заказе. 
        Для решения проблемы, попробуй пересоздать заказ. Нажмите кнопку "Отменить"
        и введите габариты упаковки повторно.';
                exit();
            }

            if (isset($order['related_entities'])) {
                foreach ($order['related_entities'] as $entity) {
                    if ($entity['type'] === 'barcode' && isset($entity['url'])) {
                        $barcodeInfo = json_decode($this->api->getBarcode($entity['uuid']), true);

                        if ($barcodeInfo['entity']['format'] !==
                            BarcodeFormat::getByIndex(Helper::getActualShippingMethod()
                                                            ->get_option('barcode_format', 0))) {
                            continue;
                        }

                        header('Content-type: application/pdf');
                        echo $this->api->getFileByLink($entity['url']);
                        exit();
                    }
                }
            }

            $barcode = json_decode($this->api->createBarcode($order['entity']['uuid']), true);

            if (!isset($barcode['entity'])) {
                echo 'Не удалось создать ШК. 
        Для решения проблемы, попробуй пересоздать заказ. Нажмите кнопку "Отменить"
        и введите габариты упаковки повторно.';
                exit();
            }

            sleep(Config::GRAPHICS_FIRST_SLEEP);

            for ($i = 0; $i < Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS; $i++) {
                $barcodeInfo = json_decode($this->api->getBarcode($barcode['entity']['uuid']), true);

                if (isset($barcodeInfo['entity']['url'])) {
                    header('Content-type: application/pdf');
                    echo $this->api->getFileByLink($barcodeInfo['entity']['url']);
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
    }
}
