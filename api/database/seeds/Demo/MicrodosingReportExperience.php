<?php

namespace Database\Seeders\Demo;

/**
 * Gravity-themed reading of the Scientific Reports microdosing survey: findings and limits only.
 */
class MicrodosingReportExperience extends ReportCourseExperience
{
    public function key(): string
    {
        return 'microdosing';
    }

    protected function academy(): array
    {
        return [
            'kicker' => 'Free · findings and limits',
            'cover' => 'Reading the microdosing survey',
            'certificate' => 'Microdosing Study — certificate',
            'palette' => [
                'background' => '#16362F',
                'text' => '#F4F1EA',
                'accent' => '#7DCEA0',
                'secondary' => '#C4A574',
                'onAccent' => '#16362F',
                'displayFont' => 'sans',
            ],
            'people' => [
                'nora' => ['first_name' => 'Nora', 'last_name' => 'Hale', 'email' => 'nora.hale@demo.ulams.app', 'role' => 'tutor', 'color' => '#7DCEA0'],
            ],
            'categories' => [
                ['parent' => 'Science', 'name' => 'Research'],
            ],
            'tags' => ['microdosing', 'survey', 'evidence', 'limits', 'free'],
        ];
    }
}
