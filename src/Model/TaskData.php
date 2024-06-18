<?php

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek\Model {

    use Cdek\Config;

    class TaskData
    {
        private string $id;
        private string $name;
        private ?string $schedule;
        private int $time;

        public function __construct(array $requestData)
        {
            $this->id = $requestData['id'];
            $this->name = $requestData['name'];
            $this->schedule = $requestData['schedule'];

            $this->time = time();
        }

        public function createTaskWork(): void
        {
            $this->time += 5 * 60;

            if ($this->isScheduledTask()) {
                if (false === as_has_scheduled_action($this->getName())) {
                    as_schedule_cron_action(
                        $this->time,
                        $this->getSchedule(),
                        sprintf('%s-%s', Config::TASK_MANAGER_HOOK_NAME, static::getName()),
                        [$this->getId()],
                        '',
                        true,
                    );
                }
            } else {
                as_enqueue_async_action(
                    sprintf('%s-%s', Config::TASK_MANAGER_HOOK_NAME, static::getName()),
                    [$this->getId()]
                );
            }
        }

        public function getId(): string
        {
            return $this->id;
        }

        public function getName(): string
        {
            return $this->name;
        }

        public function getSchedule(): ?string
        {
            return $this->schedule;
        }

        public function isScheduledTask(): bool
        {
            return $this->schedule !== null;
        }

    }
}
