<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\ShippingMethod;

    class WeightConverter
    {
        private const AVAILABLE_MEASUREMENTS
            = [
                'g'   => 1,
                'kg'  => 1000,
                'lbs' => 453.6,
                'oz'  => 28.25,
            ];

        final public static function isSupported(string $m): bool
        {
            return array_key_exists($m, self::AVAILABLE_MEASUREMENTS);
        }

        /**
         * @param  mixed  $weight
         *
         * @noinspection MissingParameterTypeDeclarationInspection
         */
        final public static function getWeightInGrams($weight): int
        {
            $m = get_option('woocommerce_weight_unit');

            if (!self::isSupported($m)) {
                $m = 'g';
            }

            return absint(ceil(self::AVAILABLE_MEASUREMENTS[$m] * self::applyFallback($weight)));
        }

        /**
         * @param  mixed  $weight
         *
         * @noinspection MissingParameterTypeDeclarationInspection
         */
        final public static function applyFallback($weight): float
        {
            if (empty($weight) || ShippingMethod::factory()->product_package_default_toggle) {
                $defaultWeight = (float)str_replace(
                    ',',
                    '.',
                    ShippingMethod::factory()->product_weight_default,
                );
                $weight        = $defaultWeight;
            }

            return (float)$weight;
        }

        /**
         * @param  mixed  $weight
         *
         * @noinspection MissingParameterTypeDeclarationInspection
         */
        final public static function getWeightInWcMeasurement($weight): float
        {
            $m = get_option('woocommerce_weight_unit');

            if (!self::isSupported($m)) {
                $m = 'g';
            }


            return $weight / self::AVAILABLE_MEASUREMENTS[$m];
        }
    }
}
