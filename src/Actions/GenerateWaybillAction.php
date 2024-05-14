<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Model\OrderMetaData;

    class GenerateWaybillAction
    {
        private CdekApi $api;

        public function __construct()
        {
            $this->api = new CdekApi;
        }

        public function __invoke(string $orderUuid): void
        {
            ini_set('max_execution_time',
                    30 +
                    Config::GRAPHICS_FIRST_SLEEP +
                    Config::GRAPHICS_TIMEOUT_SEC * Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS);

            $order = json_decode($this->api->getOrder($orderUuid), true);

            if (!isset($order['entity'])) {
                _e("Failed to retrieve order information. \n\r To solve the problem, try re-creating the order. Click the \"Cancel\" button \n\r and re-enter the package dimensions.",
                   'cdekdelivery');
                exit();
            }

            if (isset($order['related_entities'])) {
                foreach ($order['related_entities'] as $entity) {
                    if ($entity['type'] === 'waybill' && isset($entity['url'])) {
                        header("Content-type:application/pdf");
                        echo $this->api->getFileByLink($entity['url']);
                        exit();
                    }
                }
            }

            $waybill = json_decode($this->api->createWaybill($order['entity']['uuid']), true);

            if (!isset($waybill['entity'])) {
                _e("Failed to create receipt. \n\r To solve the problem, try re-creating the order. Click the \"Cancel\" button \n\r and re-enter the package dimensions.",
                   'cdekdelivery');
                exit();
            }

            sleep(Config::GRAPHICS_FIRST_SLEEP);

            for ($i = 0; $i < Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS; $i++) {
                $waybillInfo = json_decode($this->api->getWaybill($waybill['entity']['uuid']), true);

                if (isset($waybillInfo['entity']['url'])) {
                    header('Content-type: application/pdf');
                    echo $this->api->getFileByLink($waybillInfo['entity']['url']);
                    exit();
                }

                if (!isset($waybillInfo['entity']) || end($waybillInfo['entity']['statuses'])['code'] === 'INVALID') {
                    _e("Failed to create receipt. \n
                    \r To solve the problem, try requesting a receipt again",
                       'cdekdelivery');
                    exit();
                }

                sleep(Config::GRAPHICS_TIMEOUT_SEC);
            }

            _e("A request for a receipt was sent, but no response was received. \n\r To resolve the problem, try waiting 1 hour and try requesting a receipt again.",
               'cdekdelivery');

            exit();
        }
    }
}
