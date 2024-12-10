<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    use Cdek\Tasks\CollectOrphanedOrders;
    use Cdek\Tasks\Migrate;
    use Cdek\Tasks\RestoreOrderUuids;
    use DateTime;

    class TaskManager
    {
        private const TASKS
            = [
                RestoreOrderUuids::class,
                CollectOrphanedOrders::class,
                Migrate::class,
            ];

        private array $taskNames = [];

        public function __construct()
        {
            foreach (self::TASKS as $class) {
                $task = new $class;

                assert($task instanceof Contracts\TaskContract);

                add_action(
                    self::getHookName($task::getName()),
                    $class,
                    20,
                );

                $this->taskNames[] = $task::getName();
            }
        }

        public static function getHookName(string $taskName): string
        {
            return sprintf('%s-%s', Config::TASK_MANAGER_HOOK_NAME, $taskName);
        }

        public static function cancelExecution(): void
        {
            as_unschedule_all_actions(Config::TASK_MANAGER_HOOK_NAME);

            foreach (self::TASKS as $class) {
                $task = new $class;

                assert($task instanceof Contracts\TaskContract);
                as_unschedule_all_actions(self::getHookName($task::getName()));
            }
        }

        public static function scheduleExecution(): void
        {
            as_unschedule_all_actions(Config::TASK_MANAGER_HOOK_NAME);

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
                if (!in_array($task['name'], $this->taskNames, true)) {
                    continue;
                }

                $hookName = self::getHookName($task['name']);

                if ($task['schedule'] === null) {
                    if (as_has_scheduled_action($hookName, [$task['id']], 'cdekdelivery')) {
                        continue;
                    }

                    as_enqueue_async_action(
                        $hookName,
                        [$task['id']],
                        'cdekdelivery',
                    );

                    continue;
                }

                as_schedule_cron_action(
                    time() + 60,
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
