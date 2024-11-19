<?php

declare(strict_types=1);

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek\Model {

    use Cdek\ShippingMethod;

    class Service {
        public static function factory(ShippingMethod $shipping, int $tariff): array {
            if (Tariff::isToPickup($tariff) || !Tariff::availableForShops($tariff)) {
                return [];
            }

            $banAttachInspection = $shipping->services_ban_attachment_inspection;
            $tryingOn    = $shipping->services_trying_on;
            $partialDelivery = $shipping->services_part_deliv;

            $serviceList      = [];

            if ($banAttachInspection && !$tryingOn && !$partialDelivery) {
                $serviceList[] = [
                    'code' => 'BAN_ATTACHMENT_INSPECTION',
                ];
            }

            if (!$banAttachInspection && $tryingOn) {
                $serviceList[] = [
                    'code' => 'TRYING_ON',
                ];
            }

            if (!$banAttachInspection && $partialDelivery) {
                $serviceList[] = [
                    'code' => 'PART_DELIV',
                ];
            }


            return $serviceList;
        }
    }
}
