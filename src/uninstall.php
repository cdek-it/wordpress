<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

use Cdek\Uninstaller;

if (!class_exists(Uninstaller::class)) {
    trigger_error('BC Sync not fully installed! Please install with Composer or download full release archive.',
                  E_USER_ERROR);
}

(new Uninstaller)();
