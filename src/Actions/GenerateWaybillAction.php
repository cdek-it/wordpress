<?php

namespace Cdek\Actions;

use Cdek\CdekApi;
use Cdek\Config;

class GenerateWaybillAction
{
    private CdekApi $api;

    public function __construct()
    {
        $this->api = new CdekApi;
    }

    public function __invoke(int $cdekNumber): void
    {
        ini_set('max_execution_time',
                30 +
                Config::GRAPHICS_FIRST_SLEEP +
                Config::GRAPHICS_TIMEOUT_SEC * Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS);

        $order = json_decode($this->api->getOrderByCdekNumber($cdekNumber), true);

        if (!isset($order['entity'])) {
            echo 'Не удалось получить сведения о заказе. 
        Для решения проблемы, попробуй пересоздать заказ. Нажмите кнопку "Отменить"
        и введите габариты упаковки повторно.';
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
            echo 'Не удалось создать квитанцию. 
        Для решения проблемы, попробуй пересоздать заказ. Нажмите кнопку "Отменить"
        и введите габариты упаковки повторно.';
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
                echo 'Не удалось создать квитанцию. 
        Для решения проблемы, попробуй повторно запросить квитанцию.';
                exit();
            }

            sleep(Config::GRAPHICS_TIMEOUT_SEC);
        }

        echo 'Запрос на квитанцию был отправлен, но ответ по нему не пришел.
        Для решения проблемы, попробуй подождать 1 час и попробуй запросить квитанцию еще раз.';
        exit();
    }
}
