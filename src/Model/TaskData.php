<?php

namespace Cdek\Model;

use Cdek\Config;

class TaskData
{
    private $id;
    private $name;
    private $schedule;
    private int $time;

    public function __construct($requestData)
    {
        $this->id = $requestData['id'];
        $this->name = $requestData['name'];
        $this->schedule = $requestData['schedule'];

        $this->time = time();
    }

    public function createTaskWork()
    {
        $this->time += 5 * 60;

        if ($this->isScheduleTask()) {
            if (false === as_has_scheduled_action($this->getName())) {
                as_schedule_cron_action(
                    $this->time,
                    $this->getSchedule(),
                    Config::DELIVERY_NAME . '_' . Config::TASK_PREFIX . '_' . $this->getName(),
                    ['task_id' => $this->getId()],
                    '',
                    true,
                );
            }
        } else {
            as_enqueue_async_action(
                $this->time,
                Config::DELIVERY_NAME . '_' . Config::TASK_PREFIX . '_' . $this->getName(),
                ['task_id' => $this->getId()],
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

    public function isScheduleTask()
    {
        return !empty($this->schedule);
    }

}
