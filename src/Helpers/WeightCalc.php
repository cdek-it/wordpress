<?php

namespace Cdek\Helpers;

use Cdek\Helper;

class WeightCalc {

    public function getWeight($weight): int {
        $weight      = $this->getWeightInGrams((float) $weight);

        if (empty($weight) ||
            Helper::getActualShippingMethod()->get_option('product_package_default_toggle') === 'yes') {
            $defaultWeight = (float) str_replace(',', '.',
                Helper::getActualShippingMethod()->get_option('product_weight_default'));
            $weight = $this->getWeightInGrams($defaultWeight);
        }

        return (int) $weight;
    }

    protected function getWeightInGrams(float $weight): float {
        $measurement = get_option('woocommerce_weight_unit');
        switch ($measurement) {
            case 'g':
                return $weight;
            case 'kg':
                return $weight * 1000;
            case 'lbs':
                return $weight * 453.6;
            case 'oz':
                return $weight * 28.35;
        }
        return (float) $weight;
    }
}
