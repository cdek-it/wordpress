<?php

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek\Managers{

    use Cdek\Actions\Schedule\CollectOrders;
    use Cdek\Actions\Schedule\ReindexOrders;
    use Cdek\CdekCoreApi;
    use Cdek\Config;
    use Cdek\Contracts\TaskContract;
    use Cdek\Exceptions\CdekApiException;
    use Cdek\Exceptions\CdekScheduledTaskException;
    use Cdek\Model\TaskData;

    class TaskManager
    {
        private const TASK_CLASSES = [
            ReindexOrders::class,
            CollectOrders::class,
        ];
        private array $taskCollection;
        private array $taskData = [];
        private array $taskCursor = [];

        /**
         * @throws CdekApiException
         * @throws CdekScheduledTaskException
         * @throws \JsonException
         */
        public function __construct()
        {
            $this->getResponse();
            $this->initTasks();
        }

        public function start(): void
        {
            if(!isset($this->taskCollection)){
                return;
            }

            foreach ($this->taskCollection as $task) {
                $this->startTask($task);
            }
        }

        public static function init(): void
        {
            $taskManager = new self();
            $taskManager->start();
        }

        public static function getName(): string
        {
            return Config::TASK_MANAGER_HOOK_NAME;
        }

        public static function registerAction(): void
        {
            add_action(static::getName(), [static::class, 'init']);
        }

        public static function registerTasks(): void
        {
            self::registerAction();

            foreach (self::TASK_CLASSES as $arTaskClass) {
                if (is_callable([$arTaskClass, 'registerAction'])){
                    $arTaskClass::registerAction();
                }
            }
        }

        public static function getTasksHooks(): array
        {
            $arNames = [];

            foreach (self::TASK_CLASSES as $class) {
                if (is_callable([$class, 'getName'])) {
                    $arNames[] = sprintf(
                        '%s-%s',
                        Config::TASK_MANAGER_HOOK_NAME,
                        $class::getName(),
                    );
                }
            }

            return $arNames;
        }

        public static function addPluginScheduleEvents(): void
        {
            if (as_has_scheduled_action(Config::TASK_MANAGER_HOOK_NAME) !== false) {
                as_unschedule_all_actions(Config::TASK_MANAGER_HOOK_NAME);
            }

            $dateTime = new \DateTime('now + 1 hour');

            as_schedule_cron_action(
                time(),
                $dateTime->format('i') . ' ' . $dateTime->format('H') . ' * * *',
                Config::TASK_MANAGER_HOOK_NAME,
                [],
                '',
                true,
            );

        }

        private function startTask(TaskData $task): void
        {
            foreach (self::TASK_CLASSES as $class){
                if (
                    is_callable([$class, 'getName'])
                    &&
                    $task->getName() === $class::getName()
                ) {
                    $task->createTaskWork();
                }
            }
        }

        /**
         * @throws CdekApiException
         * @throws CdekScheduledTaskException
         * @throws \JsonException
         */
        private function getResponse(): void
        {
            try {
                $response = (new CdekCoreApi())->taskManager($this->taskCursor['next'] ?? null);
            } catch (CdekScheduledTaskException|CdekApiException $e) {
                static::addPluginScheduleEvents();

                throw new CdekScheduledTaskException(
                    $e->getMessage(),
                    'cdek_error.task.manager'
                );
            }

            if(
                !isset($response['cursor'])
                ||
                !array_key_exists('current', $response['cursor'])
                ||
                !array_key_exists('previous', $response['cursor'])
                ||
                !array_key_exists('next', $response['cursor'])
                ||
                !array_key_exists('count', $response['cursor'])
            ){
                throw new CdekScheduledTaskException('[CDEKDelivery] Not found cursor params',
                                                     'cdek_error.cursor.params',
                                                     $response,
                );
            }

            if (empty($this->errorCollection)) {
                $this->taskData = $response['data'];
                $this->taskCursor = $response['cursor'];
            }
        }

        private function initTasks(): void
        {
            foreach ($this->taskData as $data) {
                $this->taskCollection[] = new TaskData($data);
            }

            if(!empty($this->taskCursor['next'])){
                $this->getResponse();
                $this->initTasks();
            }
        }
    }
}
