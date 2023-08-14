<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    class Config {
        public const TRANSLATION_DOMAIN = 'official_cdek';
        public const DELIVERY_NAME = 'official_cdek';
        public const PREFIX_META = 'official_cdek_';
        public const META_KEY = 'order_data';
        public const API_URL = 'https://api.cdek.ru/v2/';
        public const TEST_API_URL = 'https://api.edu.cdek.ru/v2/';
        public const TEST_CLIENT_ID = 'EMscd6r9JnFiQ3bLoyjJY6eM78JrJceI';
        public const TEST_CLIENT_SECRET = 'PjLZkKBHEiLK3YsjtNrt3TGNG0ahs3kG';
        public const GRAPHICS_TIMEOUT_SEC = 60;
        public const GRAPHICS_FIRST_SLEEP = 2;
        public const MAX_REQUEST_RETRIES_FOR_GRAPHICS = 3;
    }
}
