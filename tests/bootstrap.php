<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Каждый файл в src/ начинается с `defined('ABSPATH') or exit;` в глобальном
// namespace-блоке — это выполняется уже при автозагрузке класса. Без константы
// PHP просто завершит процесс PHPUnit через exit().
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
