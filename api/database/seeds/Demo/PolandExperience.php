<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\Art\PolandArt;
use Database\Seeders\Demo\Support\BuildsInteractiveCourse;

/**
 * Demo academy "Poland, Measured": a free course on Poland in cited public data, read on a map and in
 * charts. Two courses share the poland package (demo-content/poland): this one in English and
 * {@see PolandPolishExperience} ("Polska w liczbach") in Polish. Each has a welcome lesson, nine
 * chapters (energy, prosperity, security, made in Poland, daily life, mobility, health, people, the
 * unfinished work; there is no education chapter because the package has no education data), a final
 * test, the certificate and a "Sources and licence" lesson. The text is in Demo/content/poland/en/ and
 * /pl/, CC BY 4.0.
 */
class PolandExperience extends DemoExperience
{
    use BuildsInteractiveCourse;

    protected function packageName(): string
    {
        return 'poland';
    }

    protected function contentDir(): string
    {
        return 'poland/en';
    }

    protected function sourcesDir(): string
    {
        return 'poland';
    }

    /** Seeds the English course, then the Polish one. @return array<string, mixed> */
    public function run(bool $refresh): array
    {
        $report = parent::run($refresh);
        if ($this->polishVariant()) {
            return $report;
        }
        $polish = (new PolandPolishExperience($this->command))->run($refresh);
        $report['extras']['polish course'] = sprintf('#%d with %d lessons, %d topics', $polish['course_id'], $polish['lessons'], array_sum($polish['topics']));
        foreach (['topics', 'questions'] as $key) {
            foreach ($polish[$key] as $type => $count) {
                $report[$key][$type] = ($report[$key][$type] ?? 0) + $count;
            }
        }
        $report['lessons'] += $polish['lessons'];
        foreach ($polish['skipped'] as $skipped) {
            $report['skipped'][] = 'pl: ' . $skipped;
        }

        return $report;
    }

    protected function polishVariant(): bool
    {
        return false;
    }

    public function key(): string
    {
        return 'poland';
    }

    protected function palette(): array
    {
        return [
            'background' => PolandArt::PAPER,
            'text' => PolandArt::INK,
            'accent' => PolandArt::RED,
            'secondary' => PolandArt::TEAL,
            'onAccent' => '#FFFFFF',
            'displayFont' => 'serif',
        ];
    }

    protected function courseFields(): array
    {
        return [
            'title' => 'Poland, measured',
            'subtitle' => 'Thirty-five years of Poland in cited public data',
            'summary' => 'Read Poland on an interactive map and in charts, one chapter at a time. Every number names its public source.',
            'description' => $this->markdown('course-description'),
            'level' => 'Beginner',
            'language' => 'en',
            'duration' => 'Self-paced',
            'hours_to_complete' => null, // lifetime access: a number is a per-learner deadline
            'target_group' => 'Anyone curious about how Poland has changed, students, teachers, journalists',
            'public' => true,
            'fields' => [
                'experience' => 'poland',
                'landing' => [
                    'headline' => 'Poland, read from the data',
                    'subheadline' => 'A free course on an interactive map and charts, with a source for every number.',
                    'cta' => 'Start the course — free',
                    'faq' => [
                        ['q' => 'Is the course free?', 'a' => 'Yes. Every chapter, quiz and the certificate are free.'],
                        ['q' => 'Where do the numbers come from?', 'a' => 'From public statistics. Each figure in a lesson carries a numbered source you can open.'],
                        ['q' => 'Is there a Polish version?', 'a' => 'A Polish course, "Polska w liczbach", is planned as a separate course with its own lessons.'],
                    ],
                    'lists' => [
                        'sources' => ['Official public statistics', 'Every chart and map layer names its source', 'Footnotes in the lesson text link to the data', 'Course text is licensed CC BY 4.0, and the last lesson lists every source'],
                        'features' => ['Explore the map', 'Read the charts', 'Check yourself with a quiz'],
                    ],
                ],
            ],
        ];
    }

    protected function people(): array
    {
        return [
            'hanna' => ['first_name' => 'Hanna', 'last_name' => 'Zielińska', 'email' => 'hanna.zielinska@demo.ulams.app', 'role' => 'tutor', 'color' => PolandArt::RED],
        ];
    }

    protected function categories(): array
    {
        return [
            ['parent' => 'Society', 'name' => 'Economics'],
            ['parent' => 'Society', 'name' => 'Geography'],
        ];
    }

    protected function tags(): array
    {
        return ['poland', 'data', 'maps', 'charts', 'free'];
    }

    protected function courseMedia(): array
    {
        return [
            'image' => $this->assets->image('course-image.png', fn () => PolandArt::cover('Free · map and charts', 'Poland, measured', 'Thirty-five years in cited public data')),
            'poster' => $this->assets->image('course-poster.png', fn () => PolandArt::cover('Free · map and charts', 'Poland, measured', 'Thirty-five years in cited public data', 1280, 720)),
            'teaser' => null,
        ];
    }

    protected function certificateName(): string
    {
        return 'Poland, Measured — certificate';
    }

    protected function program(): array
    {
        return $this->modulesProgram();
    }

    /** Free: no products, events or vouchers. */
    protected function commerce(): void
    {
    }
}
