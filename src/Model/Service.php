<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Model {

    class Service
    {
        public const SERVICES
            = [
                [
                    'code'        => 'DELIV_RECEIVER',
                    'name'        => 'ДОСТАВКА В ГОРОДЕ ПОЛУЧАТЕЛЕ',
                    'description' => 'Дополнительная услуга доставки груза в городе получателя, при условии, 
            что тариф доставки с режимом «до склада» (только для тарифов «Магистральный», «Магистральный супер-экспресс»).
            Не применимо к заказам до постаматов.',
                ],
            ];

        public static function getServiceList(): array
        {
            $serviceList = [];
            foreach (self::SERVICES as $service) {
                $serviceList[$service['code']] = $service['name'];
            }

            return $serviceList;
        }
    }
}
