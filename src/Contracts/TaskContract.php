<?php

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek\Contracts {

    use Cdek\CdekCoreApi;
    use Cdek\Config;
    use Cdek\Exceptions\CdekApiException;
    use Cdek\Exceptions\CdekScheduledTaskException;
    use Cdek\Model\TaskOutputData;
    use Cdek\Model\TaskData;

    abstract class TaskContract
    {
        protected cdekCoreApi $cdekCoreApi;
        protected ?array $taskMeta = [];
        protected string $taskId;

        public function __construct(string $taskId)
        {
            $this->cdekCoreApi = new CdekCoreApi();
            $this->taskId = $taskId;
        }

        abstract protected static function getName(): string;

        abstract function start(): void;

        public static function init(string $taskId): void
        {
            $taskManager = new static($taskId);
            $taskManager->start();
        }

        /**
         * @return void
         */
        public static function registerAction(): void
        {
            add_action(
                sprintf('%s-%s', Config::TASK_MANAGER_HOOK_NAME, static::getName()),
                [static::class, 'init'],
                20,
                1,
            );
        }

        /**
         * @throws CdekApiException
         * @throws CdekScheduledTaskException
         * @throws \JsonException
         */
        protected function getTaskMeta(): array
        {
            if(empty($this->taskMeta)){
                $this->initTaskData();
            }

            return $this->taskMeta ?? [];
        }

        /**
         * @throws CdekScheduledTaskException
         * @throws CdekApiException
         * @throws \JsonException
         */
        protected function initTaskData(array $data = null): void
        {
            $this->initData($this->cdekCoreApi->taskInfo($this->taskId, new TaskOutputData('success', $data)));
        }

        /**
         * @param array $response
         *
         * @return void
         */
        protected function initData(array $response): void
        {
            if($this->cdekCoreApi->isServerError()){
                $this->postponeTask();
                return;
            }

            $this->taskMeta = $response['data']['meta'] ?? [];
        }

        protected function postponeTask(): void
        {
            $hooks = as_get_scheduled_actions(
                [
                    'hook' => sprintf('%s-%s', Config::TASK_MANAGER_HOOK_NAME, static::getName()),
                    'status' => \ActionScheduler_Store::STATUS_PENDING,
                ],
            );

            if(empty($hooks)){
                return;
            }

            $hook = reset($hooks);

            if(!$hook->get_schedule() instanceof \ActionScheduler_CronSchedule){
                (new TaskData(
                    [
                        'id' => $this->taskId,
                        'name' => sprintf('%s-%s', Config::TASK_MANAGER_HOOK_NAME, static::getName()),
                    ],
                ))->createTaskWork();
            }
        }
    }
}
