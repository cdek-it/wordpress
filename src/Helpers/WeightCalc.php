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
            $weight = $this->getWeightInGrams($defaultWeight, 'kg');
        }

        return (int) $weight;
    }

    protected function getWeightInGrams(float $weight, string $measurement = ''): float {
        $measurement = empty($measurement) ? get_option('woocommerce_weight_unit') : $measurement;
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

        return $weight;
    }

    public function getWeightToMeasurementFromGram(float $weight, string $measurement = ''): float {
        $measurement = empty($measurement) ? get_option('woocommerce_weight_unit') : $measurement;
        switch ($measurement) {
            case 'g':
                return $weight;
            case 'kg':
                return round($weight / 1000, 3);
            case 'lbs':
                return round($weight / 453.6, 3);
            case 'oz':
                return round($weight / 28.35, 3);
        }
        return $weight;
    }
}
