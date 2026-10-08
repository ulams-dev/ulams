<?php

namespace EscolaLms\TemplatesEmail\Tasks;

class TaskAssignedVariables extends CommonTasksVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Task ":task" assigned', [
                'task' => self::VAR_TASK_TITLE,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>":task" task has been assigned to you by :creator_name</p>', [
                'user_name' => self::VAR_USER_NAME,
                'task' => self::VAR_TASK_TITLE,
                'creator_name' => self::VAR_CREATOR_NAME,
            ])),
        ];
    }
}
