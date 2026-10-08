<?php

namespace EscolaLms\TemplatesEmail\Tasks;

class TaskIncompleteVariables  extends CommonTasksVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('Incomplete task ":task"', [
                'task' =>  self::VAR_TASK_TITLE,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>Task ":task" has been marked as incomplete.</p>', [
                'user_name' => self::VAR_USER_NAME,
                'task' => self::VAR_TASK_TITLE,
            ])),
        ];
    }
}
