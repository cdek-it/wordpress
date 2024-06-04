<?php

namespace Cdek\Contracts;

use Cdek\CdekCoreApi;
use Cdek\Config;
use Cdek\Model\TaskData;

abstract class TaskContract
{
    const ORDERS_LIMIT = 10000;
    protected static array $errorCollection = [];
    protected static array $taskData = [];
    protected static array $responseCursor = [];
    protected array $headers = [];

    abstract protected static function getName(): string;
    abstract public static function init();
    public function getHeaders()
    {
        return $this->headers;
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
    protected function getTaskMeta(string $taskId): array
    {
        if(empty(self::$taskData[$taskId])){
            $this->initTaskData($taskId);
        }

        return self::$taskData[$taskId]['meta'];
    }
    protected function getTaskCursor(string $taskId): array
    {
        if(empty(self::$responseCursor[$taskId])){
            $this->initTaskData($taskId);
        }

        return self::$responseCursor[$taskId]['meta'];
    }
    protected function reportResult(int $totalPages, int $currentPage)
    {
        $this->headers = [
            'X-Total-Pages' => $totalPages,
            'X-Current-Page' => $currentPage,
        ];
    }

    protected function initTaskData(string $taskId): void
    {
        $cdekCoreApi = new CdekCoreApi();

        if(!empty($this->getHeaders())){
            $cdekCoreApi->addHeaders($this->getHeaders());
        }

        $response = $cdekCoreApi->taskManager($taskId);

        $decodeResponse = json_decode($response, true);

        if(
            $decodeResponse['error']
        ){
            self::$errorCollection[$taskId][] = $decodeResponse['error'];
        }

        if(empty($response['cursor'])){
            self::$errorCollection[$taskId][] = 'Cursor data not found';
        }

        if(empty(self::$errorCollection)){
            self::$taskData[$taskId] = new TaskData(reset($response['data']));
            self::$responseCursor[$taskId] = $response['cursor'];
        }
    }
}
