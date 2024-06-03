<?php

namespace Cdek\Model;

class TaskData
{
    const TASK_COLLECT_ORPHANED_ORDERS = 'collect_orphaned-orders';
    const TASK_RESTORE_ORDER_UUIDS = 'restore-order-uuids';

    const AVAILABLE_TASKS = [
        self::TASK_COLLECT_ORPHANED_ORDERS,
        self::TASK_RESTORE_ORDER_UUIDS,
    ];

    private $id;
    private $name;
    private $schedule;
    private $metaData;
    private $time;

    public function __construct($requestData)
    {
        $this->id = $requestData['id'];
        $this->name = $requestData['name'];
        $this->schedule = $requestData['schedule'];
        $this->metaData = [];

        if (!empty($requestData['meta'])) {
            $this->metaData = $requestData['meta'];
        }

        $this->time = time();
    }

    public function createTaskWork()
    {
        $this->time += 5 * 60;
        if ($this->schedule) {
            if (false === as_has_scheduled_action($this->name)) {
                as_schedule_cron_action(
                    $this->time,
                    $this->schedule,
                    $this->name,
                    $this->metaData,
                    '',
                    true
                );
            }
        } else {
            as_enqueue_async_action(
                $this->time,
                $this->name,
                $this->metaData
            );
        }
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
