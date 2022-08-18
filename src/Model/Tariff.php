<?php

namespace Cdek\Model;

class Tariff
{
    public const WEIGHT_5 = '5';
    public const WEIGHT_30 = '30';
    public const WEIGHT_50 = '50';
    public const DD = 'дверь-дверь (Д-Д)';
    public const SS = 'склад-склад (С-С)';
    public const SD = 'склад-дверь (С-Д)';
    public const DS = 'дверь-склад (Д-С)';
    public const DP = 'дверь-постамат (Д-П)';
    public const SP = 'склад-постамат (С-П)';
    public const MODE_FROM = [0 => [self::DD, self::DS, self::DP], 1 => [self::SS, self::SD, self::SP]];
    public const MODE_TO = [0 => [self::DD, self::SD], 1 => [self::SS, self::DS]];
    public const DOOR = '0';
    public const STORE = '1';

    public const TARIFF_DATA = [
        [
            'code' => 7,
            'name' => 'Международный экспресс документы дверь-дверь',
            'mode' => self::DD,
            'weight' => self::WEIGHT_5,
            'typeFrom' => self::DOOR,
            'typeTo' => self::DOOR,
        ],
        [
            'code' => 8,
            'name' => 'Международный экспресс грузы дверь-дверь',
            'mode' => self::DD,
            'weight' => self::WEIGHT_30,
            'typeFrom' => self::DOOR,
            'typeTo' => self::DOOR,
        ],
        [
            'code' => 136,
            'name' => 'Посылка склад-склад',
            'mode' => self::SS,
            'weight' => self::WEIGHT_30,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
        ],
        [
            'code' => 137,
            'name' => 'Посылка склад-дверь',
            'mode' => self::SD,
            'weight' => self::WEIGHT_30,
            'typeFrom' => self::STORE,
            'typeTo' => self::DOOR,
        ],
        [
            'code' => 138,
            'name' => 'Посылка дверь-склад',
            'mode' => self::DS,
            'weight' => self::WEIGHT_30,
            'typeFrom' => self::DOOR,
            'typeTo' => self::STORE,
        ],
        [
            'code' => 139,
            'name' => 'Посылка дверь-дверь',
            'mode' => self::DD,
            'weight' => self::WEIGHT_30,
            'typeFrom' => self::DOOR,
            'typeTo' => self::DOOR,
        ],
        [
            'code' => 233,
            'name' => 'Экономичная посылка склад-дверь',
            'mode' => self::SD,
            'weight' => self::WEIGHT_50,
            'typeFrom' => self::STORE,
            'typeTo' => self::DOOR,
        ],
        [
            'code' => 234,
            'name' => 'Экономичная посылка склад-склад',
            'mode' => self::SS,
            'weight' => self::WEIGHT_50,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
        ],
        [
            'code' => 291,
            'name' => 'CDEK Express склад-склад',
            'mode' => self::SS,
            'weight' => self::WEIGHT_50,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
        ],
        [
            'code' => 293,
            'name' => 'CDEK Express дверь-дверь',
            'mode' => self::DD,
            'weight' => self::WEIGHT_50,
            'typeFrom' => self::DOOR,
            'typeTo' => self::DOOR,
        ],
        [
            'code' => 294,
            'name' => 'CDEK Express склад-дверь',
            'mode' => self::SD,
            'weight' => self::WEIGHT_50,
            'typeFrom' => self::STORE,
            'typeTo' => self::DOOR,
        ],
        [
            'code' => 295,
            'name' => 'CDEK Express дверь-склад',
            'mode' => self::DS,
            'weight' => self::WEIGHT_50,
            'typeFrom' => self::DOOR,
            'typeTo' => self::STORE,
        ],
        [
            'code' => 366,
            'name' => 'Посылка дверь-постамат',
            'mode' => self::DP,
            'weight' => self::WEIGHT_30,
            'typeFrom' => self::DOOR,
            'typeTo' => self::STORE,
        ],
        [
            'code' => 368,
            'name' => 'Посылка склад-постамат',
            'mode' => self::SP,
            'weight' => self::WEIGHT_30,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
        ],
        [
            'code' => 378,
            'name' => 'Экономичная посылка склад-постамат',
            'mode' => self::SP,
            'weight' => self::WEIGHT_50,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
        ],
    ];

    public static function getTariffTypeToByCode($code)
    {
        foreach (self::TARIFF_DATA as $tariff) {
            if ($tariff['code'] == $code) {
                return $tariff['typeTo'];
            }
        }
    }

    public static function getTariffNameByCode($code)
    {
        foreach (self::TARIFF_DATA as $tariff) {
            if ($tariff['code'] == $code) {
                return $tariff['name'];
            }
        }
        return '';
    }

    public static function getTariffCodeType($code, $type)
    {
        foreach (self::TARIFF_DATA as $tariff) {
            if ($tariff['code'] == $code && in_array($tariff['mode'], self::MODE_FROM[(int)$type])) {
                return $tariff['code'];
            }
        }
        return '';
    }

    public static function getTariffList()
    {
        $tariffList = [];
        foreach (self::TARIFF_DATA as $tariff) {
            $tariffList[$tariff['code']] = $tariff['name'];
        }
        return $tariffList;
    }
}
