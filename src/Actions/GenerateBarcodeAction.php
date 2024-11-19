<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Enums\BarcodeFormat;
    use Cdek\Exceptions\External\HttpClientException;
    use Cdek\ShippingMethod;
    use Cdek\Traits\CanBeCreated;

    class GenerateBarcodeAction
    {
        use CanBeCreated;

        /**
         * @throws \Cdek\Exceptions\External\LegacyAuthException
         * @throws \Cdek\Exceptions\External\ApiException
         */
        public function __invoke(string $uuid): array
        {
            $api = new CdekApi;

            ini_set(
                'max_execution_time',
                30 +
                Config::GRAPHICS_FIRST_SLEEP +
                Config::GRAPHICS_TIMEOUT_SEC * Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS,
            );

            try {
                $order = $api->orderGet($uuid);
            } catch (HttpClientException $e) {
                return [
                    'success' => false,
                    'message' => esc_html__(
                        "Failed to create barcode.\nTry re-creating the order.\nYou may need to cancel existing one (if that button exists)",
                        'cdekdelivery',
                    ),
                ];
            }

            if ($order->entity() === null) {
                return [
                    'success' => false,
                    'message' => esc_html__(
                        "Failed to create barcode.\nTry re-creating the order.\nYou may need to cancel existing one (if that button exists)",
                        'cdekdelivery',
                    ),
                ];
            }


            foreach ($order->related() as $entity) {
                if ($entity['type'] === 'barcode' && isset($entity['url'])) {
                    $barcodeInfo = $api->barcodeGet($entity['uuid']);

                    if ($barcodeInfo['format'] !==
                        BarcodeFormat::getByIndex((int)ShippingMethod::factory()->barcode_format)) {
                        continue;
                    }

                    return [
                        'success' => true,
                        'data'    => esc_html(base64_encode($api->fileGetRaw($entity['url']))),
                    ];
                }
            }

            try {
                $barcode = $api->barcodeCreate($order->entity()['uuid']);

                if ($barcode === null) {
                    return [
                        'success' => false,
                        'message' => esc_html__(
                            "Failed to create barcode.\nTry re-creating the order.\nYou may need to cancel existing one (if that button exists)",
                            'cdekdelivery',
                        ),
                    ];
                }
            } catch (HttpClientException $e) {
                return [
                    'success' => false,
                    'message' => esc_html__(
                        "Failed to create barcode.\nTry re-creating the order.\nYou may need to cancel existing one (if that button exists)",
                        'cdekdelivery',
                    ),
                ];
            }

            sleep(Config::GRAPHICS_FIRST_SLEEP);

            for ($i = 0; $i < Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS; $i++) {
                $barcodeInfo = $api->barcodeGet($barcode);

                if (isset($barcodeInfo['url'])) {
                    return [
                        'success' => true,
                        'data'    => esc_html(base64_encode($api->fileGetRaw($barcodeInfo['url']))),
                    ];
                }

                if ($barcodeInfo === null || end($barcodeInfo['statuses'])['code'] === 'INVALID') {
                    return [
                        'success' => false,
                        'message' => esc_html__("Failed to create barcode.\nTry again", 'cdekdelivery'),
                    ];
                }

                sleep(Config::GRAPHICS_TIMEOUT_SEC);
            }

            return [
                'success' => false,
                'message' => esc_html__(
                    "A request for a barcode was sent, but no response was received.\nWait for 1 hour before trying again",
                    'cdekdelivery',
                ),
            ];
        }
    }
}
