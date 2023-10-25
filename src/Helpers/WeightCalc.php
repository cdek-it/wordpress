<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\Helper;
    use RuntimeException;

    class WeightCalc
    {

        private const G_INTO_KG = 1000;
        private const G_INTO_LBS = 453.6;
        private const G_INTO_OZ = 28.35;

        final public function getWeight(string $weight): float
        {
            if (empty($weight) ||
                Helper::getActualShippingMethod()->get_option('product_package_default_toggle') === 'yes') {
                $defaultWeight = (float)str_replace(',',
                                                    '.',
                                                    Helper::getActualShippingMethod()
                                                          ->get_option('product_weight_default'));
                $weight = $defaultWeight;
            }

            return (float)$weight;
        }

        final public function getWeightInGrams(float $weight): int
        {
            $measurement = get_option('woocommerce_weight_unit');
            switch ($measurement) {
                case 'g':
                    return (int)$weight;
                case 'kg':
                    return $this->convertToG($weight, self::G_INTO_KG);
                case 'lbs':
                    return $this->convertToG($weight, self::G_INTO_LBS);
                case 'oz':
                    return $this->convertToG($weight, self::G_INTO_OZ);
            }
            throw new RuntimeException('CDEKDelivery: The selected unit of measure is not found');
        }

        private function convertToG(float $weight, float $coefficient): int
        {
            return (int)($weight * $coefficient);
        }
    }
}
