<?php

namespace Cdek;

use Cdek\Managers\TaskManager;

class Uninstaller
{
    public function __invoke()
    {
        foreach (TaskManager::getTasksHooks() as $hook){
            if (as_has_scheduled_action($hook) !== false) {
                as_unschedule_action($hook);
            }
        }
    }
}
