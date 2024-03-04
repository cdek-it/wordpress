<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {
    class StringHelper
    {
        public static function generateRandom(int $length = 10): string
        {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[wp_rand(0, $charactersLength - 1)];
            }

            return $randomString;
        }
    }
}
