<?php

namespace Cdek\Contracts;

use Cdek\CdekCoreApi;
use Cdek\Config;
use Cdek\Model\TaskData;

abstract class TaskContract
{
    public function __construct(string $taskId)
    {
        $this->taskId = $taskId;
    }
    protected static array $errorCollection = [];
    protected static array $taskData = [];
    protected static array $responseCursor = [];
    protected array $headers = [];
    protected string $taskId;

    abstract protected static function getName(): string;
    abstract public static function init();

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
    protected function addPageHeaders(int $totalPages, int $currentPage)
    {
        $this->headers = [
            'X-Total-Pages' => $totalPages,
            'X-Current-Page' => $currentPage
        ];
    }

    protected function initTaskData($data = null): void
    {
        $cdekCoreApi = new CdekCoreApi();

        $this->initData($cdekCoreApi->taskInfo($this->taskId, $data));
    }

    protected function sendTaskData($data)
    {
        $cdekCoreApi = new CdekCoreApi();

        $cdekCoreApi->addHeaders($data);

        $this->initData($cdekCoreApi->sendTaskData($this->taskId, $data));
    }

    private function initData($response)
    {
        $decodeResponse = json_decode($response, true);

        if(
            $decodeResponse['error']
        ){
            self::$errorCollection[$this->taskId][] = $decodeResponse['error'];
        }

        if(empty($response['cursor'])){
            self::$errorCollection[$this->taskId][] = 'Cursor data not found';
        }

        if(empty(self::$errorCollection)){
            self::$taskData[$this->taskId] = new TaskData(reset($response['data']));
            self::$responseCursor[$this->taskId] = $response['cursor'];
        }

    }
}
