<?php

namespace Cdek\Model;

class TaskData
{
    const AVAILABLE_TASKS = [
        'collect_orphaned-orders',
        'restore-order-uuids',
    ];

    private $id;
    private $name;
    private $schedule;
    private $metaData;

    public function __construct($requestData)
    {
        $this->id = $requestData['id'];
        $this->name = $requestData['name'];
        $this->schedule = $requestData['schedule'];
        $this->metaData = [];

        if(!empty($requestData['meta'])){
            $this->metaData = $requestData['meta'];
        }
    }

    public function createTaskWork()
    {
        //todo logic of work task
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getSchedule()
    {
        return $this->schedule;
    }

    /**
     * @return mixed
     */
    public function getMetaData()
    {
        return $this->metaData;
    }

    public function isAvailableTask()
    {
        return in_array($this->name, self::AVAILABLE_TASKS);
    }

}
