<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\Art\PolandArt;

/**
 * Demo academy "Poland, Measured": a free course on Poland in cited public data, read on a map
 * and in charts. This is the placeholder course the tenant and its landing page run on; the
 * English course with its chapters, and the Polish course "Polska w liczbach", arrive with the
 * course content.
 */
class PolandExperience extends DemoExperience
{
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
                        'sources' => ['Official public statistics', 'Every chart and map layer names its source', 'Footnotes in the lesson text link to the data'],
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
        return [
            [
                'title' => 'Welcome',
                'summary' => 'What the course is and how to read it.',
                'duration' => '5 min',
                'topics' => [
                    [
                        'type' => 'richtext', 'title' => 'Welcome to Poland, measured', 'preview' => true, 'duration' => '5 min',
                        'introduction' => 'How the course works and what comes next.',
                        'summary' => 'Maps and charts with a source for every number.',
                        'make' => fn () => ['fields' => ['value' => $this->markdown('welcome')], 'files' => []],
                    ],
                ],
            ],
        ];
    }

    /** Free: no products, events or vouchers. */
    protected function commerce(): void
    {
    }
}
