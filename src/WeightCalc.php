<?php

namespace Cdek;

class WeightCalc
{

    public function getWeight($weight)
    {
        $weight = (float) $weight;
        if (empty($weight)) {
            $cdekShipping = WC()->shipping->load_shipping_methods()['official_cdek'];
            $cdekShippingSettings = $cdekShipping->settings;
            $weight = (float)$cdekShippingSettings['default_weight'];
        }

        $weight = $weight * 1000;


        return (int)$weight;
    }
}