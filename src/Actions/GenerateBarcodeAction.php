<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Enums\BarcodeFormat;
    use Cdek\Helper;

    class GenerateBarcodeAction
    {
        private CdekApi $api;

        public function __construct()
        {
            $this->api = new CdekApi;
        }

        public function __invoke(string $cdekUuid): array
        {
            ini_set('max_execution_time', 30 +
                                          Config::GRAPHICS_FIRST_SLEEP +
                                          Config::GRAPHICS_TIMEOUT_SEC * Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS);

            $order = json_decode($this->api->getOrder($cdekUuid), true);

            if (!isset($order['entity'])) {
                return [
                    'success' => false,
                    'message' => esc_html__("Failed to create barcode.\nTry re-creating the order.\nYou may need to cancel existing one (if that button exists)",
                                            'cdekdelivery'),
                ];
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

                        return [
                            'success' => true,
                            'data'    => esc_html(base64_encode($this->api->getFileByLink($entity['url']))),
                        ];
                    }
                }
            }

            $barcode = json_decode($this->api->createBarcode($order['entity']['uuid']), true);

            if (!isset($barcode['entity'])) {
                return [
                    'success' => false,
                    'message' => esc_html__("Failed to create barcode.\nTry re-creating the order.\nYou may need to cancel existing one (if that button exists)",
                                            'cdekdelivery'),
                ];
            }

            sleep(Config::GRAPHICS_FIRST_SLEEP);

            for ($i = 0; $i < Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS; $i++) {
                $barcodeInfo = json_decode($this->api->getBarcode($barcode['entity']['uuid']), true);

                if (isset($barcodeInfo['entity']['url'])) {
                    return [
                        'success' => true,
                        'data'    => esc_html(base64_encode($this->api->getFileByLink($barcodeInfo['entity']['url']))),
                    ];
                }

                if (!isset($barcodeInfo['entity']) || end($barcodeInfo['entity']['statuses'])['code'] === 'INVALID') {
                    return [
                        'success' => false,
                        'message' => esc_html__("Failed to create barcode.\nTry again", 'cdekdelivery'),
                    ];
                }

                sleep(Config::GRAPHICS_TIMEOUT_SEC);
            }

            return [
                'success' => false,
                'message' => esc_html__("A request for a barcode was sent, but no response was received.\nWait for 1 hour before trying again",
                                        'cdekdelivery'),
            ];
        }
    }
}
