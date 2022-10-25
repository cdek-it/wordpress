<?php

namespace Cdek;

class Helper
{
    public static function getSettingDataPlugin()
    {
        $cdekShipping = WC()->shipping->load_shipping_methods()['official_cdek'];
        return $cdekShipping->settings;
    }
}