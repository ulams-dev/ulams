<?php

namespace Database\Seeders\Demo;

/**
 * Gravity-themed reading of the November 2021 US interagency stablecoin report.
 */
class StablecoinReportExperience extends ReportCourseExperience
{
    public function key(): string
    {
        return 'stablecoins';
    }

    protected function academy(): array
    {
        return [
            'kicker' => 'Free · the November 2021 report',
            'cover' => 'Payment stablecoins',
            'certificate' => 'Stablecoin Report — certificate',
            'palette' => [
                'background' => '#1A2744',
                'text' => '#F6F3EA',
                'accent' => '#E0B15A',
                'secondary' => '#8EADD4',
                'onAccent' => '#1A2744',
                'displayFont' => 'sans',
            ],
            'people' => [
                'evan' => ['first_name' => 'Evan', 'last_name' => 'Marsh', 'email' => 'evan.marsh@demo.ulams.app', 'role' => 'tutor', 'color' => '#E0B15A'],
            ],
            'categories' => [
                ['parent' => 'Policy', 'name' => 'Finance'],
            ],
            'tags' => ['stablecoins', 'reserves', 'regulation', 'report', 'free'],
        ];
    }
}
