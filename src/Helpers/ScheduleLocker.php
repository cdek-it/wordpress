<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use ActionScheduler_Lock;

    abstract class ScheduleLocker extends ActionScheduler_Lock
    {
        private const LOCK_TYPE_AUTOMATION_ORDER = 'cdek_order_automation_lock';
        protected static $lock_duration = MONTH_IN_SECONDS;

        final public function set($lock_type): bool
        {
            return parent::set(self::LOCK_TYPE_AUTOMATION_ORDER . '_' . $lock_type);
        }
    }
}
