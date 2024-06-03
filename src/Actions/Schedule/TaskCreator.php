<?php

namespace Cdek\Actions\Schedule;

use Cdek\CdekCoreApi;
use Cdek\Model\TaskData;

class TaskCreator
{
    private TaskData $taskData;
    private array $errorCollection;
    private array $responseCursor;
    public function __construct($taskId)
    {
        $this->initTask($taskId);
    }

    public function getTask(): TaskData
    {
        return $this->taskData;
    }

    private function initTask($taskId)
    {
        $response = (new CdekCoreApi())->taskManager($taskId);
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
            $this->taskData = new TaskData(reset($response['data']));
            $this->responseCursor = $response['cursor'];
        }
    }
}
