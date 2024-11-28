<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    use Cdek\Actions\Schedule\CollectOrders;
    use Cdek\Actions\Schedule\ReindexOrders;
    use DateTime;

    class TaskManager
    {
        private const TASK_CLASSES
            = [
                'restore-order-uuids'     => ReindexOrders::class,
                'collect-orphaned-orders' => CollectOrders::class,
            ];

        public function __construct()
        {
            foreach (self::TASK_CLASSES as $key => $value) {
                add_action(
                    self::getHookName($key),
                    new $value,
                    20,
                );
            }
        }

        public static function getHookName(string $taskName): string
        {
            return sprintf('%s-%s', Config::TASK_MANAGER_HOOK_NAME, $taskName);
        }

        public static function cancelExecution(): void
        {
            as_unschedule_all_actions(Config::TASK_MANAGER_HOOK_NAME);

            foreach (array_keys(self::TASK_CLASSES) as $task) {
                as_unschedule_all_actions(self::getHookName($task));
            }
        }

        public static function scheduleExecution(): void
        {
            if (as_has_scheduled_action(Config::TASK_MANAGER_HOOK_NAME) !== false) {
                as_unschedule_all_actions(Config::TASK_MANAGER_HOOK_NAME);
            }

            $dateTime = new DateTime('now + 1 hour');

            as_schedule_cron_action(
                time(),
                "{$dateTime->format('i')} {$dateTime->format('H')} * * *",
                Config::TASK_MANAGER_HOOK_NAME,
                [],
                'cdekdelivery',
                true,
            );
        }

        /**
         * @throws \Cdek\Exceptions\CacheException
         * @throws \Cdek\Exceptions\External\ApiException
         * @throws \Cdek\Exceptions\External\CoreAuthException
         */
        public function __invoke(): void
        {
            if (!(new CoreApi)->hasToken()) {
                return;
            }

            $tasks = $this->loadTasks();

            if (empty($tasks)) {
                return;
            }

            foreach ($tasks as $task) {
                if (!isset(self::TASK_CLASSES[$task['name']])) {
                    continue;
                }

                $hookName = self::getHookName($task['name']);

                if ($task['schedule'] === null) {
                    as_enqueue_async_action(
                        $hookName,
                        [$task['id']],
                    );

                    continue;
                }

                if (!empty($existingAction = as_get_scheduled_actions(['hook' => $hookName])) &&
                    $existingAction[0]['schedule'] === $task['schedule']) {
                    continue;
                }

                as_schedule_cron_action(
                    time() + 5 * 60,
                    $task['schedule'],
                    $hookName,
                    [$task['id']],
                    'cdekdelivery',
                    true,
                );
            }
        }

        /**
         * @throws \Cdek\Exceptions\External\ApiException
         * @throws \Cdek\Exceptions\External\CoreAuthException
         * @throws \Cdek\Exceptions\CacheException
         */
        private function loadTasks(?string $cursor = null): array
        {
            $response = (new CoreApi)->taskList($cursor);

            $tasks = $response->data();

            if ($response->nextCursor() !== null) {
                array_push($tasks, ...$this->loadTasks($response->nextCursor()));
            }

            return $tasks;
        }
    }
}
