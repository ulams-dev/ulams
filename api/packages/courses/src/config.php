<?php

use Ulams\Courses\Enum\CoursesConstant;
use Ulams\Courses\Enum\CourseVisibilityEnum;
use Ulams\Courses\Enum\PlatformVisibility;

return [
    'platform_visibility' => PlatformVisibility::VISIBILITY_PUBLIC,
    'reminder_of_deadline_count_days' => CoursesConstant::REMINDER_OF_DEADLINE_COUNT_DAYS,
    'course_visibility' => CourseVisibilityEnum::SHOW_ALL,
    'topic_resource_mimes' => env('TOPIC_RESOURCE_FILES_MIMES', 'avi,mov,mp3,mp4,wmv,pdf,doc,docx,ppt,png,jpg,jpeg,gif,zip'),
];
