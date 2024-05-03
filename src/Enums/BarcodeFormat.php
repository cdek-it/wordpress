<?php

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\Enums {

    use RuntimeException;

    final class BarcodeFormat
    {
        private const AVAILABLE_VALUES = ['A4', 'A5', 'A6'];

        private string $value;

        public function __construct(string $value)
        {

            if (!in_array($value, self::AVAILABLE_VALUES)) {
                throw new RuntimeException('Not supported value!');
            }

            $this->value = $value;
        }

        /**
         * @return string[]
         */
        public static function getAll(): array
        {
            return self::AVAILABLE_VALUES;
        }

        public static function getByIndex(int $index): string
        {
            return self::AVAILABLE_VALUES[$index];
        }

        public function __toString(): string
        {
            return $this->value;
        }
    }
}
