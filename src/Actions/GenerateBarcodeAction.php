<?php
declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Enums\BarcodeFormat;
    use Cdek\Helper;
    use Cdek\Traits\CanBeCreated;

    class GenerateBarcodeAction
    {
        use CanBeCreated;
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

            $order = $this->api->getOrder($cdekUuid);

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
                        $barcodeInfo = $this->api->getBarcode($entity['uuid']);

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

            $barcode = $this->api->createBarcode($order['entity']['uuid']);

            if (!isset($barcode['entity'])) {
                return [
                    'success' => false,
                    'message' => esc_html__("Failed to create barcode.\nTry re-creating the order.\nYou may need to cancel existing one (if that button exists)",
                                            'cdekdelivery'),
                ];
            }

            sleep(Config::GRAPHICS_FIRST_SLEEP);

            for ($i = 0; $i < Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS; $i++) {
                $barcodeInfo = $this->api->getBarcode($barcode['entity']['uuid']);

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
