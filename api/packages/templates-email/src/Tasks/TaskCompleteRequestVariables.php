<?php

namespace EscolaLms\TemplatesEmail\Tasks;

class TaskCompleteRequestVariables  extends CommonTasksVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __(':assignee_name has completed the task', [
                'assignee_name' =>  self::VAR_ASSIGNEE_NAME,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>:assignee_name has been marked task ":task" as completed.</p>', [
                'user_name' => self::VAR_USER_NAME,
                'task' => self::VAR_TASK_TITLE,
                'assignee_name' =>  self::VAR_ASSIGNEE_NAME,
            ])),
        ];
    }
}
