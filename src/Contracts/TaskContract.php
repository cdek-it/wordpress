<?php

namespace Cdek\Contracts;

use Cdek\CdekCoreApi;
use Cdek\Config;
use Cdek\Exceptions\CdekApiException;
use Cdek\Model\TaskData;

abstract class TaskContract
{
    const FINISH_STATUS = 201;
    const RESTART_STATUS = 202;
    const UNKNOWN_METHOD = 404;
    const FATAL_ERRORS = [500, 502, 503];
    protected cdekCoreApi $cdekCoreApi;

    public function __construct(string $taskId)
    {
        $this->cdekCoreApi = new CdekCoreApi();
        $this->taskId = $taskId;
    }
    protected static array $errorCollection = [];
    protected static array $taskData = [];
    protected static array $responseCursor = [];
    protected string $taskId;
    protected int $status;

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
            $this->initTaskData($this->taskId);
        }

        return self::$taskData[$this->taskId]['meta'];
    }
    protected function getTaskCursor(): array
    {
        if(empty(self::$responseCursor[$this->taskId])){
            $this->initTaskData($this->taskId);
        }

        return self::$responseCursor[$this->taskId]['meta'];
    }

    protected function initTaskData($data = null): void
    {
        $this->initData($this->cdekCoreApi->taskInfo($this->taskId, $data));
    }

    protected function sendTaskData($data)
    {
        $this->initData($this->cdekCoreApi->sendTaskData($this->taskId, $data));
    }

    private function initData($response)
    {
        $this->status = $response['status'];

        if(
            !in_array(
                $this->status,
                [self::FINISH_STATUS, self::RESTART_STATUS],
            )
        ){
            if(in_array($this->status, self::FATAL_ERRORS)){
                $this->postponeTask();
                return;
            }else{
                throw new CdekApiException('[CDEKDelivery] Failed to get core api response',
                                           'cdek_error.core.response',
                                           $response,
                                           true);

            }

        }

        $decodeResponse = json_decode($response['body'], true);

        if(
            empty($decodeResponse['success'])
        ){
            self::$errorCollection[$this->taskId][] = __('Request to api was failed', 'cdekdelivery');
        }

        if(empty($response['cursor'])){
            self::$errorCollection[$this->taskId][] = __('Cursor data not found', 'cdekdelivery');
        }

        if(empty(self::$errorCollection)){
            self::$taskData[$this->taskId] = new TaskData(reset($response['data']));
            self::$responseCursor[$this->taskId] = $response['cursor'];
        }
    }

    protected function postponeTask()
    {
        //todo finish that and start next one later
    }
}
