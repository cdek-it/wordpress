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
                _e("Failed to retrieve order information.\n\r
                To solve the problem, try re-creating the order.\n\r
                Click the \"Cancel\" button and enter the package dimensions again.",
                   'cdekdelivery');
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
                _e("Failed to create Barcode. \n\r To solve the problem, try re-creating the order. Click the \"Cancel\" button \n\r and enter the package dimensions again.",
                   'cdekdelivery');
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
                    _e("Failed to create Barcode.\n\r To solve the problem, try requesting Barcode again.", 'cdekdelivery');
                    exit();
                }

                sleep(Config::GRAPHICS_TIMEOUT_SEC);
            }

            _e("A request to Barcode was sent, but no response was received. \n\r To solve the problem, try to wait 1 hour and try to request the Barcode again.",
               'cdekdelivery');

            exit();
        }
    }
}
