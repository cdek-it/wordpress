<?php
declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Traits\CanBeCreated;

    class GenerateWaybillAction
    {
        use CanBeCreated;

        private CdekApi $api;

        public function __construct()
        {
            $this->api = new CdekApi;
        }

        public function __invoke(string $orderUuid): array
        {
            ini_set('max_execution_time', 30 +
                                          Config::GRAPHICS_FIRST_SLEEP +
                                          Config::GRAPHICS_TIMEOUT_SEC * Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS);

            $order = $this->api->getOrder($orderUuid);

            if (!isset($order['entity'])) {
                return [
                    'success' => false,
                    'message' => esc_html__("Failed to create waybill.\nTo solve the problem, try re-creating the order.\nYou may need to cancel existing one (if that button exists)",
                                            'cdekdelivery'),
                ];
            }

            if (isset($order['related_entities'])) {
                foreach ($order['related_entities'] as $entity) {
                    if ($entity['type'] === 'waybill' && isset($entity['url'])) {
                        return [
                            'success' => true,
                            'data'    => esc_html(base64_encode($this->api->getFileByLink($entity['url']))),
                        ];
                    }
                }
            }

            $waybill = $this->api->createWaybill($order['entity']['uuid']);

            if (!isset($waybill['entity'])) {
                return [
                    'success' => false,
                    'message' => esc_html__("Failed to create waybill.\nTry re-creating the order.\nYou may need to cancel existing one (if that button exists)",
                                            'cdekdelivery'),
                ];
            }

            sleep(Config::GRAPHICS_FIRST_SLEEP);

            for ($i = 0; $i < Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS; $i++) {
                $waybillInfo = $this->api->getWaybill($waybill['entity']['uuid']);

                if (isset($waybillInfo['entity']['url'])) {
                    return [
                        'success' => true,
                        'data'    => esc_html(base64_encode($this->api->getFileByLink($waybillInfo['entity']['url']))),
                    ];
                }

                if (!isset($waybillInfo['entity']) || end($waybillInfo['entity']['statuses'])['code'] === 'INVALID') {
                    return [
                        'success' => false,
                        'message' => esc_html__("Failed to create waybill.\nTry again", 'cdekdelivery'),
                    ];
                }

                sleep(Config::GRAPHICS_TIMEOUT_SEC);
            }

            return [
                'success' => false,
                'message' => esc_html__("A request for a waybill was sent, but no response was received.\nWait for 1 hour before trying again",
                                        'cdekdelivery'),
            ];
        }
    }
}
