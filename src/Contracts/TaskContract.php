<?php

namespace Cdek\Contracts;

use Cdek\CdekCoreApi;
use Cdek\Config;
use Cdek\Model\TaskData;

abstract class TaskContract
{
    protected cdekCoreApi $cdekCoreApi;

    public function __construct(string $taskId)
    {
        $this->cdekCoreApi = new CdekCoreApi();
        $this->taskId = $taskId;
    }
    protected static array $errorCollection = [];
    protected static array $taskData = [];
    protected static array $responseCursor = [];
    protected array $headers = [];
    protected string $taskId;

    abstract protected static function getName(): string;
    abstract public static function init($taskId);

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
}
