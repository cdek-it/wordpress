<?php

namespace Cdek\Contracts;

use Cdek\CdekCoreApi;
use Cdek\Config;
use Cdek\Model\CoreRequestData;
use Cdek\Model\TaskData;

abstract class TaskContract
{
    protected cdekCoreApi $cdekCoreApi;
    protected static array $errorCollection = [];
    protected static array $taskData = [];
    protected static array $responseCursor = [];
    protected string $taskId;

    public function __construct(string $taskId)
    {
        $this->cdekCoreApi = new CdekCoreApi();
        $this->taskId = $taskId;
    }

    abstract protected static function getName(): string;

    abstract function start();

    public static function init($taskId = 'task_manager'): void
    {
        $taskManager = new static($taskId);
        $taskManager->start();
    }

    public static function registerAction(): void
    {
        add_action(
            sprintf('%s-%s', Config::TASK_MANAGER_HOOK_NAME, static::getName()),
            [static::class, 'init'],
            20,
            1,
        );
    }
    protected function getTaskMeta(): array
    {
        if(empty(self::$taskData[$this->taskId])){
            $this->initTaskData();
        }

        return self::$taskData[$this->taskId]['meta'] ?? [];
    }

    protected function getTaskData(): array
    {
        if(empty(self::$taskData[$this->taskId])){
            $this->initTaskData();
        }

        return self::$taskData[$this->taskId] ?? [];
    }

    protected function getTaskCursor(): array
    {
        if(empty(self::$responseCursor[$this->taskId])){
            $this->initTaskData();
        }

        return self::$responseCursor[$this->taskId]['meta'];
    }

    protected function initTaskData(array $data = null): void
    {
        $this->initData($this->cdekCoreApi->taskInfo($this->taskId, new CoreRequestData('success', $data)));
    }

    protected function initData($response)
    {
        if($this->cdekCoreApi->isServerError()){
            $this->postponeTask();
            return;
        }

        if(
            empty($response['success'])
        ){
            self::$errorCollection[$this->taskId][] = __('Request to api was failed', 'cdekdelivery');
        }

        if(empty(self::$errorCollection[$this->taskId])){
            self::$taskData[$this->taskId] = $response['data'];
            self::$responseCursor[$this->taskId] = $response['cursor'];
        }
    }

    protected function postponeTask()
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
