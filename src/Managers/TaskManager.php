<?php

namespace Cdek\Managers;

use Cdek\Actions\Schedule\CollectOrders;
use Cdek\Actions\Schedule\ReindexOrders;
use Cdek\CdekCoreApi;
use Cdek\Config;
use Cdek\Contracts\TaskContract;
use Cdek\Exceptions\CdekApiException;
use Cdek\Exceptions\CdekScheduledTaskException;
use Cdek\Model\TaskData;

class TaskManager extends TaskContract
{
    private const TASK_CLASSES = [
        self::class,
        ReindexOrders::class,
        CollectOrders::class,
    ];

    private array $taskCollection;
    private array $taskData = [];

    /**
     * @param $taskId
     *
     * @throws CdekApiException
     * @throws CdekScheduledTaskException
     * @throws \JsonException
     */
    public function __construct($taskId)
    {
        parent::__construct($taskId);
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
        foreach (self::TASK_CLASSES as $arTaskClass) {
            if ('\\' . $arTaskClass instanceof TaskContract) {
                '\\' . $arTaskClass::registerAction();
            }
        }
    }

    public static function getTasksHooks(): array
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

    public static function addPluginScheduleEvents(): void
    {
        if (as_has_scheduled_action(Config::TASK_MANAGER_HOOK_NAME) !== false) {
            as_unschedule_action(Config::TASK_MANAGER_HOOK_NAME);
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
        if (!in_array(
            $task->getName(),
            array_map(
                static fn($class) => $class::getName(),
                self::TASK_CLASSES
            )
        )
        ) {
            return;
        }

        $task->createTaskWork();
    }

    /**
     * @return void
     * @throws CdekApiException
     * @throws CdekScheduledTaskException
     * @throws \JsonException
     */
    private function getResponse(): void
    {
        try {
            $response = (new CdekCoreApi())->taskManager();
        } catch (CdekScheduledTaskException $e) {
            static::addPluginScheduleEvents();

            throw new CdekScheduledTaskException(
                $e->getMessage(),
                'cdek_error.task.manager'
            );
        }

        if (empty($this->errorCollection)) {
            $this->taskData = $response['data'];
        }
    }

    private function initTasks(): void
    {
        foreach ($this->taskData as $data) {
            $this->taskCollection[] = new TaskData($data);
        }
    }
}
