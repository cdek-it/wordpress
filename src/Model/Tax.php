<?php

declare(strict_types=1);

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek\Model {
    class Tax
    {
        private const NO_TAX = null;
        private const TAX_ZERO = 0;
        private const TAX_5 = 5;
        private const TAX_10 = 10;
        private const TAX_12 = 12;
        private const TAX_20 = 20;
        private const DEFAULT_TAX = self::NO_TAX;

        private const AVAILABLE_TAX = [
            self::NO_TAX,
            self::TAX_ZERO,
            self::TAX_5,
            self::TAX_10,
            self::TAX_12,
            self::TAX_20,
        ];

        public static function getTax($rateClass): int
        {
            $taxRates = \WC_Tax::get_rates_for_tax_class($rateClass);

            if(is_array($taxRates)){
                if(count($taxRates) == 0){
                    return self::DEFAULT_TAX;
                }

                $taxValue = intval(
                    round(
                        array_sum(
                            array_map(static fn($tax) => $tax->tax_rate, $taxRates)
                        )
                    )
                );

                if(in_array($taxValue, self::AVAILABLE_TAX)){
                    return $taxValue;
                }
            }

            return self::DEFAULT_TAX;
        }
    }
}
