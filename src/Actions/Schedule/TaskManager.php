<?php

namespace Cdek\Actions\Schedule;

use Cdek\CdekCoreApi;
use Cdek\Model\TaskData;

class TaskManager
{
    private array $responseData = [];
    private array $responseCursor;
    private TaskData $task;
    private array $taskCollection;
    private ?array $errorCollection;

    public function __construct()
    {
        $this->getResponse();
        $this->initTasks();
    }

    public function init()
    {
        $taskManager = new self();
        $taskManager->startTasksWork();
    }

    public function startTasksWork()
    {
        foreach ($this->taskCollection as $task){
            $this->startTask($task);
        }
    }

    public function getErrors()
    {
        return $this->errorCollection;
    }

    private function startTask(TaskData $task)
    {
        if(!$task->isAvailableTask()){
            return;
        }

        $task->createTaskWork();
    }

    private function getResponse()
    {
        $response = (new CdekCoreApi())->taskManager();
        $decodeResponse = json_decode($response, true);

        if(
            $decodeResponse['error']
        ){
            $this->errorCollection[] = $decodeResponse['error'];
        }

        if(empty($response['cursor'])){
            $this->errorCollection[] = 'Cursor data not found';
        }

        if(empty($this->errorCollection)){
            $this->responseData = $response['data'];
            $this->responseCursor = $response['cursor'];
        }
    }

    private function initTasks()
    {
        if(!empty($this->errorCollection)){
            return;
        }

        foreach ($this->responseData as $data){
            $this->taskCollection[] = new TaskData($data);
        }
    }
}
