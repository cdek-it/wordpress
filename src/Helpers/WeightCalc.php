<?php

namespace Cdek\Helpers;

use Cdek\Helper;

class WeightCalc {

    public function getWeight($weight): int {
        $weight      = (float) $weight;
        $measurement = get_option('woocommerce_weight_unit');
        if (empty($weight)) {
            $defaultWeight = (float) str_replace(',', '.',
                Helper::getActualShippingMethod()->get_option('product_weight_default'));
            if ($measurement === 'g') {
                $weight = $defaultWeight * 1000;
            } else {
                $weight = $defaultWeight;
            }
        }

        if ($measurement === 'kg') {
            $weight *= 1000;
        }

        return (int) $weight;
    }
}
