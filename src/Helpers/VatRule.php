<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    class VatRule
    {
        private const NO_COST_VAT = 0;
        private const ADD_VAT_TO_PRICE = 1;
        private const REMOVE_VAT_FROM_PRICE = 2;

        public static function data(): array
        {
            return [
                self::NO_COST_VAT => esc_html__('no cost vat', 'cdekdelivery'),
                self::ADD_VAT_TO_PRICE => esc_html__('add vat to price', 'cdekdelivery'),
                self::REMOVE_VAT_FROM_PRICE => esc_html__('remove vat from price', 'cdekdelivery'),
            ];
        }

        public static function calculate(float $itemPrice, float $vatPrice, int $vatRule): float
        {
            switch ($vatRule) {
                case self::ADD_VAT_TO_PRICE:
                    return $itemPrice + $vatPrice;
                case self::REMOVE_VAT_FROM_PRICE:
                    return $itemPrice - $vatPrice;
                case self::NO_COST_VAT:
                default:
                    return $itemPrice;
            }
        }
    }
}
