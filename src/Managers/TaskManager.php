<?php

namespace Cdek\Managers;

use Cdek\Actions\Schedule\CollectOrders;
use Cdek\Actions\Schedule\ReindexOrders;
use Cdek\CdekCoreApi;
use Cdek\Config;
use Cdek\Contracts\TaskContract;
use Cdek\Model\TaskData;

class TaskManager extends TaskContract
{
    private const TASK_CLASSES = [
        self::class,
        ReindexOrders::class,
        CollectOrders::class,
    ];

    private array $taskCollection;

    public function __construct($taskId)
    {
        parent::__construct($taskId);
        $this->getResponse();
        $this->initTasks();
    }

    public function start(): void
    {
        foreach ($this->taskCollection as $task) {
            $this->startTask($task);
        }
    }

    public static function getName(): string
    {
        return Config::TASK_MANAGER_HOOK_NAME;
    }

    public static function registerAction(): void
    {
        add_action(static::getName(), [static::class, 'init']);
    }

    public function getErrors(): array
    {
        return self::$errorCollection[$this->taskId];
    }


    public static function registerTasks(): void
    {
        foreach (self::TASK_CLASSES as $arTaskClass) {
            if ('\\' . $arTaskClass instanceof TaskContract) {
                '\\' . $arTaskClass::registerAction();
            }
        }
    }

    public static function getTasksHooks()
    {
        return array_map(
            static fn($class) => $class::getName() === static::getName() ?
                static::getName() :
                sprintf('%s-%s',
                        Config::TASK_MANAGER_HOOK_NAME,
                        $class::getName(),
                ),
            self::TASK_CLASSES,
        );
    }

    private function startTask(TaskData $task): void
    {
        if (!in_array(
            $task->getName(),
            array_map(
                static fn($class) => $class::getName(),
                self::TASK_CLASSES,
            ),
        )
        ) {
            return;
        }

        $task->createTaskWork();
    }

    private function getResponse(): void
    {
        $response = (new CdekCoreApi())->taskManager();
        $decodeResponse = json_decode($response['body'], true);

        if (
            $decodeResponse['error']
        ) {
            self::$errorCollection[$this->taskId][] = $decodeResponse['error'];
        }

        if (empty($decodeResponse['cursor'])) {
            self::$errorCollection[$this->taskId][] = 'Cursor data not found';
        }

        if (empty($this->errorCollection)) {
            self::$taskData[$this->taskId] = $decodeResponse['data'];
            self::$responseCursor[$this->taskId] = $decodeResponse['cursor'];
        }
    }

    private function initTasks(): void
    {
        if (!empty(self::$errorCollection)) {
            return;
        }

        foreach (self::$taskData[$this->taskId] as $data) {
            $this->taskCollection[] = new TaskData($data);
        }
    }
}
