<?php

/**
 * Стаб для PHPStan. Action Scheduler поставляется как часть WooCommerce/сторонних
 * плагинов и не имеет официального пакета стабов на Packagist.
 */

class ActionScheduler_Lock
{
    /** @var int */
    protected static $lock_duration;

    /**
     * @param string $lock_type
     */
    public function set($lock_type): bool
    {
        return true;
    }
}

/**
 * @param array<mixed> $args
 * @param string|false $group
 * @return int|bool
 */
function as_schedule_single_action(int $timestamp, string $hook, array $args = [], $group = false, bool $unique = false)
{
}

/**
 * @param array<mixed> $args
 * @param string|false $group
 * @return int|bool
 */
function as_schedule_cron_action(int $timestamp, string $schedule, string $hook, array $args = [], $group = false, bool $unique = false)
{
}

/**
 * @param array<mixed> $args
 * @param string|false $group
 */
function as_enqueue_async_action(string $hook, array $args = [], $group = false): int
{
}

/**
 * @param array<mixed> $args
 * @param string|false $group
 */
function as_unschedule_all_actions(string $hook, array $args = [], $group = false): void
{
}

/**
 * @param array<mixed>|null $args
 * @param string|false $group
 * @return string|int|bool
 */
function as_has_scheduled_action(string $hook, $args = null, $group = false)
{
}
