<?php

namespace Cdek\Model;

use Cdek\Helper;
use RuntimeException;

class Tariff {
    private const DOOR_DOOR = 'дверь-дверь (Д-Д)';
    private const DOOR_OFFICE = 'дверь-склад (Д-С)';
    private const OFFICE_DOOR = 'склад-дверь (С-Д)';
    private const OFFICE_OFFICE = 'склад-склад (С-С)';
    private const DOOR_PICKUP = 'дверь-постамат (Д-П)';
    private const OFFICE_PICKUP = 'склад-постамат (С-П)';
    private const PICKUP_DOOR = 'постамат-дверь (П-Д)';
    private const PICKUP_OFFICE = 'постамат-склад (П-С)';
    private const PICKUP_PICKUP = 'постамат-постамат (П-П)';

    public const DELIVERY_TYPE = 2;
    public const SHOP_TYPE = 1;

    private const TARIFF_DATA = [
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
        122 => [
            'name' => 'Магистральный экспресс склад-дверь',
            'mode' => self::OFFICE_DOOR,
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
            'name' => 'CDEK Express склад-склад',
            'mode' => self::OFFICE_OFFICE,
            'type' => self::SHOP_TYPE,
        ],
        293 => [
            'name' => 'CDEK Express дверь-дверь',
            'mode' => self::DOOR_DOOR,
            'type' => self::SHOP_TYPE,
        ],
        294 => [
            'name' => 'CDEK Express склад-дверь',
            'mode' => self::OFFICE_DOOR,
            'type' => self::SHOP_TYPE,
        ],
        295 => [
            'name' => 'CDEK Express дверь-склад',
            'mode' => self::DOOR_OFFICE,
            'type' => self::SHOP_TYPE,
        ],
        361 => [
            'name' => 'Экспресс лайт дверь-постамат',
            'mode' => self::DOOR_PICKUP,
            'type' => self::SHOP_TYPE,
        ],
        363 => [
            'name' => 'Экспресс лайт склад-постамат',
            'mode' => self::OFFICE_PICKUP,
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
        749 => [
            'name' => 'Сборный груз дверь-склад',
            'mode' => self::DOOR_OFFICE,
            'type' => self::DELIVERY_TYPE,
        ],
        751 => [
            'name' => 'Сборный груз склад-склад',
            'mode' => self::OFFICE_OFFICE,
            'type' => self::DELIVERY_TYPE,
        ],
    ];

    public static function getTariffType(int $code): int
    {
        if (!isset(self::TARIFF_DATA[$code])) {
            throw new RuntimeException('Unknown tariff');
        }

        return self::TARIFF_DATA[$code]['type'];
    }

    public static function isTariffToOffice(int $code): bool {
        if (!isset(self::TARIFF_DATA[$code])) {
            throw new RuntimeException('Unknown tariff');
        }

        return self::TARIFF_DATA[$code]['mode'] === self::DOOR_OFFICE ||
               self::TARIFF_DATA[$code]['mode'] === self::OFFICE_OFFICE ||
               self::TARIFF_DATA[$code]['mode'] === self::PICKUP_OFFICE ||
               self::TARIFF_DATA[$code]['mode'] === self::PICKUP_PICKUP ||
               self::TARIFF_DATA[$code]['mode'] === self::OFFICE_PICKUP ||
               self::TARIFF_DATA[$code]['mode'] === self::DOOR_PICKUP;
    }

    public static function isTariffFromOffice(int $code): bool {
        if (!isset(self::TARIFF_DATA[$code])) {
            throw new RuntimeException('Unknown tariff');
        }

        return self::TARIFF_DATA[$code]['mode'] === self::OFFICE_DOOR ||
               self::TARIFF_DATA[$code]['mode'] === self::OFFICE_OFFICE ||
               self::TARIFF_DATA[$code]['mode'] === self::OFFICE_PICKUP;
    }

    public static function isTariffFromDoor(int $code): bool {
        if (!isset(self::TARIFF_DATA[$code])) {
            throw new RuntimeException('Unknown tariff');
        }

        return self::TARIFF_DATA[$code]['mode'] === self::DOOR_DOOR ||
               self::TARIFF_DATA[$code]['mode'] === self::DOOR_OFFICE ||
               self::TARIFF_DATA[$code]['mode'] === self::DOOR_PICKUP;
    }

    public static function getTariffUserNameByCode(int $code) {
        if (!isset(self::TARIFF_DATA[$code])) {
            throw new RuntimeException('Unknown tariff');
        }

        $tariffNameEdit = Helper::getActualShippingMethod()->get_option('tariff_name');
        if (!empty($tariffNameEdit)) {
            $tariffNameEditArray = explode(';', $tariffNameEdit);
            foreach ($tariffNameEditArray as $tariffEdit) {
                $tariffConcrete = explode('-', $tariffEdit);
                if ($tariffConcrete[0] === $code) {
                    return $tariffConcrete[1];
                }
            }
        }

        return self::TARIFF_DATA[$code]['name'];
    }

    public static function getTariffList(): array {
        return array_map(static fn(array $el) => $el['name'], self::TARIFF_DATA);
    }
}
