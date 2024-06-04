<?php

namespace Cdek;

use Cdek\Managers\TaskManager;

class Uninstaller
{
    public function __invoke()
    {
        foreach (TaskManager::getTasksHooks() as $hook){
            if (as_has_scheduled_action(Config::TASK_MANAGER_HOOK_NAME) === false) {
                wp_unschedule_event(
                    time(),
                    $hook
                );
            }
        }
    }
}
