<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Model {

    use Cdek\ShippingMethod;
    use RuntimeException;

    class Tariff
    {
        private const DOOR_DOOR = 1;
        private const DOOR_OFFICE = 2;
        private const OFFICE_DOOR = 3;
        private const OFFICE_OFFICE = 4;
        private const DOOR_PICKUP = 6;
        private const OFFICE_PICKUP = 7;
        private const PICKUP_DOOR = 8;
        private const PICKUP_OFFICE = 9;
        private const PICKUP_PICKUP = 10;

        public const DELIVERY_TYPE = 2;
        public const SHOP_TYPE = 1;

        private const DATA
            = [
                7   => [
                    'name' => 'Международный экспресс документы дверь-дверь',
                    'mode' => self::DOOR_DOOR,
                    'type' => self::SHOP_TYPE,
                ],
                8   => [
                    'name' => 'Международный экспресс грузы дверь-дверь',
                    'mode' => self::DOOR_DOOR,
                    'type' => self::SHOP_TYPE,
                ],
                62  => [
                    'name' => 'Магистральный экспресс склад-склад',
                    'mode' => self::OFFICE_OFFICE,
                    'type' => self::SHOP_TYPE,
                ],
                121 => [
                    'name' => 'Магистральный экспресс дверь-дверь',
                    'mode' => self::DOOR_DOOR,
                    'type' => self::SHOP_TYPE,
                ],
                122 => [
                    'name' => 'Магистральный экспресс склад-дверь',
                    'mode' => self::OFFICE_DOOR,
                    'type' => self::SHOP_TYPE,
                ],
                123 => [
                    'name' => 'Магистральный экспресс дверь-склад',
                    'mode' => self::DOOR_OFFICE,
                    'type' => self::SHOP_TYPE,
                ],
                136 => [
                    'name' => 'Посылка склад-склад',
                    'mode' => self::OFFICE_OFFICE,
                    'type' => self::SHOP_TYPE,
                ],
                137 => [
                    'name' => 'Посылка склад-дверь',
                    'mode' => self::OFFICE_DOOR,
                    'type' => self::SHOP_TYPE,
                ],
                138 => [
                    'name' => 'Посылка дверь-склад',
                    'mode' => self::DOOR_OFFICE,
                    'type' => self::SHOP_TYPE,
                ],
                139 => [
                    'name' => 'Посылка дверь-дверь',
                    'mode' => self::DOOR_DOOR,
                    'type' => self::SHOP_TYPE,
                ],
                184 => [
                    'name' => 'E-com Standard дверь-дверь',
                    'mode' => self::DOOR_DOOR,
                    'type' => self::SHOP_TYPE,
                ],
                185 => [
                    'name' => 'E-com Standard склад-склад',
                    'mode' => self::OFFICE_OFFICE,
                    'type' => self::SHOP_TYPE,
                ],
                186 => [
                    'name' => 'E-com Standard склад-дверь',
                    'mode' => self::OFFICE_DOOR,
                    'type' => self::SHOP_TYPE,
                ],
                187 => [
                    'name' => 'E-com Standard дверь-склад',
                    'mode' => self::DOOR_OFFICE,
                    'type' => self::SHOP_TYPE,
                ],
                231 => [
                    'name' => 'Экономичная посылка дверь-дверь',
                    'mode' => self::DOOR_DOOR,
                    'type' => self::SHOP_TYPE,
                ],
                232 => [
                    'name' => 'Экономичная посылка дверь-склад',
                    'mode' => self::DOOR_OFFICE,
                    'type' => self::SHOP_TYPE,
                ],
                233 => [
                    'name' => 'Экономичная посылка склад-дверь',
                    'mode' => self::OFFICE_DOOR,
                    'type' => self::SHOP_TYPE,
                ],
                234 => [
                    'name' => 'Экономичная посылка склад-склад',
                    'mode' => self::OFFICE_OFFICE,
                    'type' => self::SHOP_TYPE,
                ],
                291 => [
                    'name' => 'E-com Express склад-склад',
                    'mode' => self::OFFICE_OFFICE,
                    'type' => self::SHOP_TYPE,
                ],
                293 => [
                    'name' => 'E-com Express дверь-дверь',
                    'mode' => self::DOOR_DOOR,
                    'type' => self::SHOP_TYPE,
                ],
                294 => [
                    'name' => 'E-com Express склад-дверь',
                    'mode' => self::OFFICE_DOOR,
                    'type' => self::SHOP_TYPE,
                ],
                295 => [
                    'name' => 'E-com Express дверь-склад',
                    'mode' => self::DOOR_OFFICE,
                    'type' => self::SHOP_TYPE,
                ],
                366 => [
                    'name' => 'Посылка дверь-постамат',
                    'mode' => self::DOOR_PICKUP,
                    'type' => self::SHOP_TYPE,
                ],
                368 => [
                    'name' => 'Посылка склад-постамат',
                    'mode' => self::OFFICE_PICKUP,
                    'type' => self::SHOP_TYPE,
                ],
                376 => [
                    'name' => 'Экономичная посылка дверь-постамат',
                    'mode' => self::DOOR_PICKUP,
                    'type' => self::SHOP_TYPE,
                ],
                378 => [
                    'name' => 'Экономичная посылка склад-постамат',
                    'mode' => self::OFFICE_PICKUP,
                    'type' => self::SHOP_TYPE,
                ],
                480 => [
                    'name' => 'Экспресс дверь-дверь',
                    'mode' => self::DOOR_DOOR,
                    'type' => self::SHOP_TYPE,
                ],
                481 => [
                    'name' => 'Экспресс дверь-склад',
                    'mode' => self::DOOR_OFFICE,
                    'type' => self::SHOP_TYPE,
                ],
                482 => [
                    'name' => 'Экспресс склад-дверь',
                    'mode' => self::OFFICE_DOOR,
                    'type' => self::SHOP_TYPE,
                ],
                483 => [
                    'name' => 'Экспресс склад-склад',
                    'mode' => self::OFFICE_OFFICE,
                    'type' => self::SHOP_TYPE,
                ],
                485 => [
                    'name' => 'Экспресс дверь-постамат',
                    'mode' => self::DOOR_PICKUP,
                    'type' => self::SHOP_TYPE,
                ],
                486 => [
                    'name' => 'Экспресс склад-постамат',
                    'mode' => self::OFFICE_PICKUP,
                    'type' => self::SHOP_TYPE,
                ],
                497 => [
                    'name' => 'E-com Standard дверь-постамат',
                    'mode' => self::DOOR_PICKUP,
                    'type' => self::SHOP_TYPE,
                ],
                498 => [
                    'name' => 'E-com Standard склад-постамат',
                    'mode' => self::OFFICE_PICKUP,
                    'type' => self::SHOP_TYPE,
                ],
                748 => [
                    'name' => 'Сборный груз дверь-дверь',
                    'mode' => self::DOOR_DOOR,
                    'type' => self::DELIVERY_TYPE,
                ],
                749 => [
                    'name' => 'Сборный груз дверь-склад',
                    'mode' => self::DOOR_OFFICE,
                    'type' => self::DELIVERY_TYPE,
                ],
                750 => [
                    'name' => 'Сборный груз склад-дверь',
                    'mode' => self::OFFICE_DOOR,
                    'type' => self::DELIVERY_TYPE,
                ],
                751 => [
                    'name' => 'Сборный груз склад-склад',
                    'mode' => self::OFFICE_OFFICE,
                    'type' => self::DELIVERY_TYPE,
                ],
            ];

        public static function getType(int $code): int
        {
            if (!isset(self::DATA[$code])) {
                throw new RuntimeException("Unknown tariff $code");
            }

            return self::DATA[$code]['type'];
        }

        public static function isToOffice(int $code): bool
        {
            if (!isset(self::DATA[$code])) {
                throw new RuntimeException("Unknown tariff $code");
            }

            return self::DATA[$code]['mode'] === self::DOOR_OFFICE ||
                   self::DATA[$code]['mode'] === self::OFFICE_OFFICE ||
                   self::DATA[$code]['mode'] === self::PICKUP_OFFICE ||
                   self::DATA[$code]['mode'] === self::PICKUP_PICKUP ||
                   self::DATA[$code]['mode'] === self::OFFICE_PICKUP ||
                   self::DATA[$code]['mode'] === self::DOOR_PICKUP;
        }

        public static function isFromOffice(int $code): bool
        {
            if (!isset(self::DATA[$code])) {
                throw new RuntimeException("Unknown tariff $code");
            }

            return self::DATA[$code]['mode'] === self::OFFICE_DOOR ||
                   self::DATA[$code]['mode'] === self::OFFICE_OFFICE ||
                   self::DATA[$code]['mode'] === self::OFFICE_PICKUP;
        }

        public static function isFromDoor(int $code): bool
        {
            if (!isset(self::DATA[$code])) {
                throw new RuntimeException("Unknown tariff $code");
            }

            return self::DATA[$code]['mode'] === self::DOOR_DOOR ||
                   self::DATA[$code]['mode'] === self::DOOR_OFFICE ||
                   self::DATA[$code]['mode'] === self::DOOR_PICKUP;
        }

        public static function getName(int $code, string $name): string
        {
            $tariffNameEdit = ShippingMethod::factory()->tariff_name;

            if ( !empty($tariffNameEdit) ) {
                $tariffNameEditArray = explode(';', $tariffNameEdit);

                foreach ($tariffNameEditArray as $tariffEdit) {
                    $tariffConcrete = explode('-', $tariffEdit);
                    if ( ($tariffConcrete[0] === (string)$code) && !empty($tariffConcrete[1]) ) {
                        return $tariffConcrete[1];
                    }
                }
            }

            return "CDEK: $name";
        }

        public static function list(): array
        {
            return array_combine(
                array_keys(self::DATA),
                array_map(static fn(int $code, array $el) => sprintf('%s (%s)', $el['name'], $code),
                    array_keys(self::DATA),
                    self::DATA),
            );
        }

        public static function listOfficeDeliveryModes(): array
        {
            return [
                self::OFFICE_OFFICE,
                self::DOOR_OFFICE,
                self::DOOR_PICKUP,
                self::PICKUP_OFFICE,
                self::PICKUP_PICKUP,
                self::OFFICE_PICKUP,
            ];
        }

        public static function isToPickup(int $code): bool
        {
            if (!isset(self::DATA[$code])) {
                throw new RuntimeException("Unknown tariff $code");
            }

            return self::DATA[$code]['mode'] === self::DOOR_PICKUP ||
                   self::DATA[$code]['mode'] === self::OFFICE_PICKUP ||
                   self::DATA[$code]['mode'] === self::PICKUP_PICKUP;
        }

        public static function availableForShops(int $code): bool
        {
            if (!isset(self::DATA[$code])) {
                throw new RuntimeException("Unknown tariff $code");
            }

            return self::DATA[$code]['type'] === self::SHOP_TYPE;
        }
    }
}
