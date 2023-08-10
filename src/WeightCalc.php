<?php

namespace Cdek;

class WeightCalc
{

    public function getWeight($weight)
    {
        $weight = (float) $weight;
        $measurement = get_option('woocommerce_weight_unit');
        if (empty($weight)) {
            $cdekShipping = WC()->shipping->load_shipping_methods()[Config::DELIVERY_NAME];
            $cdekShippingSettings = $cdekShipping->settings;
            $defaultWeight = (float)str_replace(',', '.', $cdekShippingSettings['product_weight_default']);
            if ($measurement === 'g') {
                $weight = $defaultWeight * 1000;
            } else {
                $weight = $defaultWeight;
            }
        }

        if ($measurement === 'kg') {
            $weight = $weight * 1000;
        }

        return (int)$weight;
    }
}
