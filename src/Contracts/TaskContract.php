<?php

namespace Cdek\Contracts;

use Cdek\CdekCoreApi;
use Cdek\Config;
use Cdek\Exceptions\CdekCoreApiException;
use Cdek\Model\TaskData;

abstract class TaskContract
{
    const SUCCESS_STATUS = 200;
    const FINISH_STATUS = 201;
    const RESTART_STATUS = 202;
    const UNKNOWN_METHOD = 404;
    const FATAL_ERRORS = [500, 502, 503];
    protected cdekCoreApi $cdekCoreApi;
    protected static array $errorCollection = [];
    protected static array $taskData = [];
    protected static array $responseCursor = [];
    protected string $taskId;
    protected ?int $status;

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
    protected function getTaskCursor(): array
    {
        if(empty(self::$responseCursor[$this->taskId])){
            $this->initTaskData();
        }

        return self::$responseCursor[$this->taskId]['meta'];
    }

    protected function initTaskData(array $data = null): void
    {
        $this->initData($this->cdekCoreApi->taskInfo($this->taskId, $data));
    }

    protected function sendTaskData(array $data, $headers = [])
    {
        $this->initData($this->cdekCoreApi->sendTaskData($this->taskId, $data, $headers));
    }

    private function initData($response)
    {
        $decodeResponse = json_decode($response['body'], true);

        $this->status = $decodeResponse['status'];

        if(
            !in_array(
                $this->status,
                [self::FINISH_STATUS, self::RESTART_STATUS, self::SUCCESS_STATUS],
            )
        ){
            if(in_array($this->status, self::FATAL_ERRORS)){
                $this->postponeTask();
                return;
            }else{
                throw new CdekCoreApiException('[CDEKDelivery] Failed to get core api response' . var_export($response, true),
                                               'cdek_error.core.response',
                                               $response,
                                               true);

            }
        }

        if(
            empty($decodeResponse['success'])
        ){
            self::$errorCollection[$this->taskId][] = __('Request to api was failed', 'cdekdelivery');
        }

        if(empty(self::$errorCollection[$this->taskId])){
            self::$taskData[$this->taskId] = $decodeResponse['data'];
            self::$responseCursor[$this->taskId] = $decodeResponse['cursor'];
        }
    }

    protected function postponeTask()
    {
        $hooks = as_get_scheduled_actions(
            [
                'hook' => sprintf('%s-%s', Config::TASK_MANAGER_HOOK_NAME, static::getName()),
                'status' => \ActionScheduler_Store::STATUS_PENDING
            ]
        );

        if(empty($hooks)){
            return;
        }

        $hook = reset($hooks);

        if(!$hook->get_schedule() instanceof \ActionScheduler_CronSchedule){
            (new TaskData(
                [
                    'id' => $this->taskId,
                    'name' => sprintf('%s-%s', Config::TASK_MANAGER_HOOK_NAME, static::getName())
                ]
            ))->createTaskWork();
        }
    }
}
