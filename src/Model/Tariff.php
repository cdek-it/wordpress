<?php

namespace Cdek\Model;

use Cdek\Helper;

class Tariff
{
    public const WEIGHT_5 = '5';
    public const WEIGHT_30 = '30';
    public const WEIGHT_50 = '50';
    public const WEIGHT_500 = '500';
    public const WEIGHT_100000 = '100000';
    public const WEIGHT_20000 = '20000';
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
            'postamat' => false
        ],
        [
            'code' => 8,
            'name' => 'Международный экспресс грузы дверь-дверь',
            'mode' => self::DD,
            'weight' => self::WEIGHT_30,
            'typeFrom' => self::DOOR,
            'typeTo' => self::DOOR,
            'postamat' => false
        ],
        [
            'code' => 136,
            'name' => 'Посылка склад-склад',
            'mode' => self::SS,
            'weight' => self::WEIGHT_30,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
            'postamat' => false
        ],
        [
            'code' => 137,
            'name' => 'Посылка склад-дверь',
            'mode' => self::SD,
            'weight' => self::WEIGHT_30,
            'typeFrom' => self::STORE,
            'typeTo' => self::DOOR,
            'postamat' => false
        ],
        [
            'code' => 138,
            'name' => 'Посылка дверь-склад',
            'mode' => self::DS,
            'weight' => self::WEIGHT_30,
            'typeFrom' => self::DOOR,
            'typeTo' => self::STORE,
            'postamat' => false
        ],
        [
            'code' => 139,
            'name' => 'Посылка дверь-дверь',
            'mode' => self::DD,
            'weight' => self::WEIGHT_30,
            'typeFrom' => self::DOOR,
            'typeTo' => self::DOOR,
            'postamat' => false
        ],
        [
            'code' => 233,
            'name' => 'Экономичная посылка склад-дверь',
            'mode' => self::SD,
            'weight' => self::WEIGHT_50,
            'typeFrom' => self::STORE,
            'typeTo' => self::DOOR,
            'postamat' => false
        ],
        [
            'code' => 234,
            'name' => 'Экономичная посылка склад-склад',
            'mode' => self::SS,
            'weight' => self::WEIGHT_50,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
            'postamat' => false
        ],
        [
            'code' => 291,
            'name' => 'CDEK Express склад-склад',
            'mode' => self::SS,
            'weight' => self::WEIGHT_500,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
            'postamat' => false
        ],
        [
            'code' => 293,
            'name' => 'CDEK Express дверь-дверь',
            'mode' => self::DD,
            'weight' => self::WEIGHT_500,
            'typeFrom' => self::DOOR,
            'typeTo' => self::DOOR,
            'postamat' => false
        ],
        [
            'code' => 294,
            'name' => 'CDEK Express склад-дверь',
            'mode' => self::SD,
            'weight' => self::WEIGHT_500,
            'typeFrom' => self::STORE,
            'typeTo' => self::DOOR,
            'postamat' => false
        ],
        [
            'code' => 295,
            'name' => 'CDEK Express дверь-склад',
            'mode' => self::DS,
            'weight' => self::WEIGHT_500,
            'typeFrom' => self::DOOR,
            'typeTo' => self::STORE,
            'postamat' => false
        ],
        [
            'code' => 366,
            'name' => 'Посылка дверь-постамат',
            'mode' => self::DP,
            'weight' => self::WEIGHT_30,
            'typeFrom' => self::DOOR,
            'typeTo' => self::STORE,
            'postamat' => true
        ],
        [
            'code' => 368,
            'name' => 'Посылка склад-постамат',
            'mode' => self::SP,
            'weight' => self::WEIGHT_30,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
            'postamat' => true
        ],
        [
            'code' => 378,
            'name' => 'Экономичная посылка склад-постамат',
            'mode' => self::SP,
            'weight' => self::WEIGHT_50,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
            'postamat' => true
        ],
        [
            'code' => 62,
            'name' => 'Магистральный экспресс склад-склад',
            'mode' => self::SS,
            'weight' => self::WEIGHT_100000,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
            'postamat' => false
        ],
        [
            'code' => 122,
            'name' => 'Магистральный экспресс склад-дверь',
            'mode' => self::SD,
            'weight' => self::WEIGHT_100000,
            'typeFrom' => self::STORE,
            'typeTo' => self::DOOR,
            'postamat' => false
        ],
        [
            'code' => 480,
            'name' => 'Экспресс дверь-дверь',
            'mode' => self::DD,
            'weight' => self::WEIGHT_100000,
            'typeFrom' => self::DOOR,
            'typeTo' => self::DOOR,
            'postamat' => false
        ],
        [
            'code' => 481,
            'name' => 'Экспресс дверь-склад',
            'mode' => self::DS,
            'weight' => self::WEIGHT_100000,
            'typeFrom' => self::DOOR,
            'typeTo' => self::STORE,
            'postamat' => false
        ],
        [
            'code' => 482,
            'name' => 'Экспресс склад-дверь',
            'mode' => self::SD,
            'weight' => self::WEIGHT_100000,
            'typeFrom' => self::STORE,
            'typeTo' => self::DOOR,
            'postamat' => false
        ],
        [
            'code' => 483,
            'name' => 'Экспресс склад-склад',
            'mode' => self::SS,
            'weight' => self::WEIGHT_100000,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
            'postamat' => false
        ],
        [
            'code' => 485,
            'name' => 'Экспресс дверь-постамат',
            'mode' => self::DP,
            'weight' => self::WEIGHT_100000,
            'typeFrom' => self::DOOR,
            'typeTo' => self::STORE,
            'postamat' => true
        ],
        [
            'code' => 486,
            'name' => 'Экспресс склад-постамат',
            'mode' => self::SP,
            'weight' => self::WEIGHT_100000,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
            'postamat' => true
        ],
        [
            'code' => 184,
            'name' => 'E-com Standard дверь-дверь',
            'mode' => self::SP,
            'weight' => self::WEIGHT_100000,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
            'postamat' => false
        ],
        [
            'code' => 185,
            'name' => 'E-com Standard склад-склад',
            'mode' => self::SP,
            'weight' => self::WEIGHT_100000,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
            'postamat' => false
        ],
        [
            'code' => 186,
            'name' => 'E-com Standard склад-дверь',
            'mode' => self::SP,
            'weight' => self::WEIGHT_100000,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
            'postamat' => false
        ],
        [
            'code' => 187,
            'name' => 'E-com Standard дверь-склад',
            'mode' => self::SP,
            'weight' => self::WEIGHT_100000,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
            'postamat' => false
        ],
        [
            'code' => 497,
            'name' => 'E-com Standard дверь-постамат',
            'mode' => self::SP,
            'weight' => self::WEIGHT_100000,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
            'postamat' => true
        ],
        [
            'code' => 498,
            'name' => 'E-com Standard склад-постамат',
            'mode' => self::SP,
            'weight' => self::WEIGHT_100000,
            'typeFrom' => self::STORE,
            'typeTo' => self::STORE,
            'postamat' => true
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

    public static function isTariffEndPointPostamatByCode($code)
    {
        foreach (self::TARIFF_DATA as $tariff) {
            if ($tariff['code'] == $code) {
                return $tariff['postamat'];
            }
        }
    }

    public static function isTariffToStoreByCode($code)
    {
        foreach (self::TARIFF_DATA as $tariff) {
            if ($tariff['code'] == $code) {
                if ($tariff['typeTo'] === self::STORE) {
                    return 1;
                } else {
                    return 0;
                }
            }
        }
    }

    public static function isTariffFromStoreByCode($code)
    {
        foreach (self::TARIFF_DATA as $tariff) {
            if ($tariff['code'] == $code) {
                if ($tariff['typeFrom'] === self::STORE) {
                    return 1;
                } else {
                    return 0;
                }
            }
        }
    }

    public static function getTariffWeightByCode($code)
    {
        foreach (self::TARIFF_DATA as $tariff) {
            if ($tariff['code'] == $code) {
                return $tariff['weight'];
            }
        }

        return 0;
    }

    public static function getTariffNameByCode($code)
    {
        $setting = Helper::getSettingDataPlugin();
        $tariffNameEdit = $setting['tariff_name'];
        if (!empty($tariffNameEdit)) {
            $tariffNameEditArray = explode(';', $tariffNameEdit);
            $tariffEditList = [];
            foreach ($tariffNameEditArray as $tariffEdit) {
                $tariffConcrete = explode('-', $tariffEdit);
                $tariffEditList[$tariffConcrete[0]] = $tariffConcrete[1];
            }
        } else {
            $tariffEditList = [];
        }

        foreach (self::TARIFF_DATA as $tariff) {
            if ($tariff['code'] == $code) {
                foreach ($tariffEditList as $codeTariff => $tariffEdit) {
                    if ((int)$code === (int)$codeTariff && !empty($tariffEdit)) {
                        return $tariffEdit;
                    }
                }

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
            $tariffList[$tariff['code']] = $tariff['name'] . ' (' . $tariff['code'] . ')';
        }
        return $tariffList;
    }
}
