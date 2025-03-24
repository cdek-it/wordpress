<?php

declare(strict_types=1);

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek\Model {
    use WC_Tax;

    class Tax
    {
        private const array AVAILABLE_TAX = [
            null, 0, 5, 10, 12, 20
        ];

        public static function getTax(string $rateClass): ?int
        {
            $taxRates = WC_Tax::get_rates_for_tax_class($rateClass);

            if(!is_array($taxRates)){
                return self::AVAILABLE_TAX[0];
            }

            if(count($taxRates) === 0){
                return self::AVAILABLE_TAX[0];
            }

            $taxValue = (int)round(
                array_sum(
                    array_map(static fn($tax) => $tax->tax_rate, $taxRates),
                ),
            );

            if(in_array($taxValue, self::AVAILABLE_TAX, true)){
                return $taxValue;
            }

            return self::AVAILABLE_TAX[0];
        }
    }
}
