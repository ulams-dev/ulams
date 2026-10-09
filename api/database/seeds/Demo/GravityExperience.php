<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\Art\GravityArt;

/**
 * Demo academy "Gravity Lab": a free, interactive course on how gravity shapes the solar
 * system. This is the placeholder course the tenant and its landing page run on; the
 * modules, the 3D simulation topics and the quizzes arrive with the course content.
 */
class GravityExperience extends DemoExperience
{
    public function key(): string
    {
        return 'gravity';
    }

    protected function palette(): array
    {
        return [
            'background' => GravityArt::SPACE,
            'text' => GravityArt::TEXT,
            'accent' => GravityArt::CYAN,
            'secondary' => GravityArt::GOLD,
            'onAccent' => GravityArt::SPACE,
            'displayFont' => 'sans',
        ];
    }

    protected function courseFields(): array
    {
        return [
            'title' => 'How gravity shapes the solar system',
            'subtitle' => 'A free course you learn inside a live simulation',
            'summary' => 'Explore orbits, tides and escape velocity in an interactive solar system, read the short explanation beside it and check yourself with a quiz.',
            'description' => $this->markdown('course-description'),
            'level' => 'Beginner',
            'language' => 'en',
            'duration' => 'Self-paced',
            'hours_to_complete' => null, // lifetime access: a number is a per-learner deadline
            'target_group' => 'Curious adults and students who want to see gravity at work',
            'public' => true,
            'fields' => [
                'experience' => 'gravity',
                'landing' => [
                    'headline' => 'Learn gravity by flying through it',
                    'subheadline' => 'A free course built around a live, real-data solar system.',
                    'cta' => 'Start the course — free',
                    'faq' => [
                        ['q' => 'Is the course free?', 'a' => 'Yes. Every lesson, quiz and the certificate are free.'],
                        ['q' => 'Do I need a powerful computer?', 'a' => 'No. The simulation runs in a modern browser. Every step also has a text version and a poster for devices without 3D or for reduced motion.'],
                        ['q' => 'Is it only a video?', 'a' => 'No. You move through the simulation yourself and each step is explained in text beside it.'],
                    ],
                    'lists' => [
                        'sources' => ['Planet and moon data comes from public NASA and JPL tables', 'Every figure shown in the lessons names its source'],
                        'features' => ['Explore the live simulation', 'Read the short explanation', 'Check yourself with a quiz'],
                    ],
                ],
            ],
        ];
    }

    protected function people(): array
    {
        return [
            'iris' => ['first_name' => 'Iris', 'last_name' => 'Vale', 'email' => 'iris.vale@demo.ulams.app', 'role' => 'tutor', 'color' => GravityArt::CYAN],
        ];
    }

    protected function categories(): array
    {
        return [
            ['parent' => 'Science', 'name' => 'Space'],
            ['parent' => 'Science', 'name' => 'Physics'],
        ];
    }

    protected function tags(): array
    {
        return ['gravity', 'orbits', 'solar system', 'simulation', 'free'];
    }

    protected function courseMedia(): array
    {
        return [
            'image' => $this->assets->image('course-image.png', fn () => GravityArt::cover('Free · interactive', 'How gravity shapes the solar system', 'Learn inside a live simulation')),
            'poster' => $this->assets->image('course-poster.png', fn () => GravityArt::cover('Free · interactive', 'How gravity shapes the solar system', 'Learn inside a live simulation', 1280, 720)),
            'teaser' => null,
        ];
    }

    protected function certificateName(): string
    {
        return 'Gravity Lab — certificate';
    }

    protected function program(): array
    {
        return [
            [
                'title' => 'Welcome',
                'summary' => 'What the course is and how to use it.',
                'duration' => '5 min',
                'topics' => [
                    [
                        'type' => 'richtext', 'title' => 'Welcome to Gravity Lab', 'preview' => true, 'duration' => '5 min',
                        'introduction' => 'How the course works and what comes next.',
                        'summary' => 'Interactive lessons with a text version of every step.',
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
