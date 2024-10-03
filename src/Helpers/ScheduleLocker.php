<?php
declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {
    class ScheduleLocker extends \ActionScheduler_OptionLock
    {
        const LOCK_TYPE_AUTOMATION_ORDER = 'cdek_order_automation_lock';
        protected static $lock_duration = MONTH_IN_SECONDS;

        public static function instance()
        {
            return new self();
        }
    }
}
