<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    class Config
    {
        public const DELIVERY_NAME = 'official_cdek';
        public const META_KEY = 'order_data';
        public const ORDER_META_BOX_KEY = 'official_cdek_order';
        public const ORDER_AUTOMATION_HOOK_NAME = 'cdekdelivery_automation';
        public const TASK_MANAGER_HOOK_NAME = 'cdekdelivery_task_manager';
        public const API_CORE_URL = 'https://api.cdek.ru/';
        public const API_VERSION = 'v2/';
        public const API_URL = 'https://api.cdek.ru/' . self::API_VERSION;
        public const TEST_API_URL = 'https://api.edu.cdek.ru/' . self::API_VERSION;
        public const TEST_CLIENT_ID = 'wqGwiQx0gg8mLtiEKsUinjVSICCjtTEP';
        public const TEST_CLIENT_SECRET = 'RmAmgvSgSl1yirlz9QupbzOJVqhCxcP5';
        public const GRAPHICS_TIMEOUT_SEC = 60;
        public const GRAPHICS_FIRST_SLEEP = 2;
        public const MAX_REQUEST_RETRIES_FOR_GRAPHICS = 3;
        public const DEV_KEY = '7wV8tk&r6VH4zK:1&0uDpjOkvM~qngLl';
        public const DOCS_URL = 'https://github.com/cdek-it/wordpress/wiki/';
        public const FAQ_URL = 'https://github.com/cdek-it/wordpress/wiki/FAQ';
    }
}
