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

    private const TARIFF_DATA = [
        7   => [
            'name' => 'Международный экспресс документы дверь-дверь',
            'mode' => self::DOOR_DOOR,
        ],
        8   => [
            'name' => 'Международный экспресс грузы дверь-дверь',
            'mode' => self::DOOR_DOOR,
        ],
        62  => [
            'name' => 'Магистральный экспресс склад-склад',
            'mode' => self::OFFICE_OFFICE,
        ],
        122 => [
            'name' => 'Магистральный экспресс склад-дверь',
            'mode' => self::OFFICE_DOOR,
        ],
        136 => [
            'name' => 'Посылка склад-склад',
            'mode' => self::OFFICE_OFFICE,
        ],
        137 => [
            'name' => 'Посылка склад-дверь',
            'mode' => self::OFFICE_DOOR,
        ],
        138 => [
            'name' => 'Посылка дверь-склад',
            'mode' => self::DOOR_OFFICE,
        ],
        139 => [
            'name' => 'Посылка дверь-дверь',
            'mode' => self::DOOR_DOOR,
        ],
        184 => [
            'name' => 'E-com Standard дверь-дверь',
            'mode' => self::DOOR_DOOR,
        ],
        185 => [
            'name' => 'E-com Standard склад-склад',
            'mode' => self::OFFICE_OFFICE,
        ],
        186 => [
            'name' => 'E-com Standard склад-дверь',
            'mode' => self::OFFICE_DOOR,
        ],
        187 => [
            'name' => 'E-com Standard дверь-склад',
            'mode' => self::DOOR_OFFICE,
        ],
        231 => [
            'name' => 'Экономичная посылка дверь-дверь',
            'mode' => self::DOOR_DOOR,
        ],
        232 => [
            'name' => 'Экономичная посылка дверь-склад',
            'mode' => self::DOOR_OFFICE,
        ],
        233 => [
            'name' => 'Экономичная посылка склад-дверь',
            'mode' => self::OFFICE_DOOR,
        ],
        234 => [
            'name' => 'Экономичная посылка склад-склад',
            'mode' => self::OFFICE_OFFICE,
        ],
        291 => [
            'name' => 'CDEK Express склад-склад',
            'mode' => self::OFFICE_OFFICE,
        ],
        293 => [
            'name' => 'CDEK Express дверь-дверь',
            'mode' => self::DOOR_DOOR,
        ],
        294 => [
            'name' => 'CDEK Express склад-дверь',
            'mode' => self::OFFICE_DOOR,
        ],
        295 => [
            'name' => 'CDEK Express дверь-склад',
            'mode' => self::DOOR_OFFICE,
        ],
        361 => [
            'name' => 'Экспресс лайт дверь-постамат',
            'mode' => self::DOOR_PICKUP,
        ],
        363 => [
            'name' => 'Экспресс лайт склад-постамат',
            'mode' => self::OFFICE_PICKUP,
        ],
        366 => [
            'name' => 'Посылка дверь-постамат',
            'mode' => self::DOOR_PICKUP,
        ],
        368 => [
            'name' => 'Посылка склад-постамат',
            'mode' => self::OFFICE_PICKUP,
        ],
        376 => [
            'name' => 'Экономичная посылка дверь-постамат',
            'mode' => self::DOOR_PICKUP,
        ],
        378 => [
            'name' => 'Экономичная посылка склад-постамат',
            'mode' => self::OFFICE_PICKUP,
        ],
        480 => [
            'name' => 'Экспресс дверь-дверь',
            'mode' => self::DOOR_DOOR,
        ],
        481 => [
            'name' => 'Экспресс дверь-склад',
            'mode' => self::DOOR_OFFICE,
        ],
        482 => [
            'name' => 'Экспресс склад-дверь',
            'mode' => self::OFFICE_DOOR,
        ],
        483 => [
            'name' => 'Экспресс склад-склад',
            'mode' => self::OFFICE_OFFICE,
        ],
        485 => [
            'name' => 'Экспресс дверь-постамат',
            'mode' => self::DOOR_PICKUP,
        ],
        486 => [
            'name' => 'Экспресс склад-постамат',
            'mode' => self::OFFICE_PICKUP,
        ],
        497 => [
            'name' => 'E-com Standard дверь-постамат',
            'mode' => self::DOOR_PICKUP,
        ],
        498 => [
            'name' => 'E-com Standard склад-постамат',
            'mode' => self::OFFICE_PICKUP,
        ],
        751 => [
            'name' => 'Сборный груз склад-склад',
            'mode' => self::OFFICE_OFFICE,
        ],
    ];

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
