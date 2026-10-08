<?php

namespace EscolaLms\Tasks\Enums;

use EscolaLms\Core\Enums\BasicEnum;

class TaskPermissionEnum extends BasicEnum
{
    const CREATE_OWN_TASK = 'task_create-own';
    const UPDATE_OWN_TASK = 'task_update-own';
    const DELETE_OWN_TASK = 'task_delete-own';
    const LIST_OWN_TASK = 'task_list-own';
    const FIND_OWN_TASK = 'task_find-own';

    const CREATE_TASK = 'task_create';
    const UPDATE_TASK = 'task_update';
    const DELETE_TASK = 'task_delete';
    const LIST_TASK = 'task_list';
    const FIND_TASK = 'task_find';

    const CREATE_OWN_TASK_NOTE = 'task-note_create-own';
    const UPDATE_OWN_TASK_NOTE = 'task-note_update-own';
    const DELETE_OWN_TASK_NOTE = 'task-note_delete-own';

    const CREATE_TASK_NOTE = 'task-note_create';
    const UPDATE_TASK_NOTE = 'task-note_update';
    const DELETE_TASK_NOTE = 'task-note_delete';
}
