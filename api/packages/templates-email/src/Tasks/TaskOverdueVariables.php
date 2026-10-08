<?php

namespace EscolaLms\TemplatesEmail\Tasks;

class TaskOverdueVariables extends CommonTasksVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Task ":task" is overdue', [
                'task' => self::VAR_TASK_TITLE,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>Your task ":task" is overdue</p>', [
                'user_name' => self::VAR_USER_NAME,
                'task' => self::VAR_TASK_TITLE,
            ])),
        ];
    }
}
