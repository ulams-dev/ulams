<?php

namespace EscolaLms\TemplatesEmail\Tasks;

class TaskNoteCreatedVariables extends CommonTasksNoteVariables
{
    public static function defaultSectionsContent(): array
    {
        return [
            'title' => __('New note for the ":task" task', [
                'task' => self::VAR_TASK_TITLE,
            ]),
            'content' => self::wrapWithMjml(__('<h1>Hello :user_name!</h1><p>:creator_name added a new note to the ":task" task.</p>', [
                'user_name' => self::VAR_USER_NAME,
                'task' => self::VAR_TASK_TITLE,
                'creator_name' => self::VAR_CREATOR_NAME,
            ])),
        ];
    }
}
