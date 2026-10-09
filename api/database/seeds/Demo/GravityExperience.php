<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\Art\GravityArt;
use Database\Seeders\Demo\Support\BuildsInteractiveCourse;

/**
 * Demo academy "Gravity Lab": a free, interactive course on how gravity shapes the solar
 * system. Nine modules follow the walkthrough of the gravity package (demo-content/gravity),
 * each with Interactive topics pinned to a step range, a short explanation, a Layout topic and
 * a GIFT quiz, then a final test, the certificate and a "Sources and licence" lesson. The text
 * is in Demo/content/gravity/modules/, CC BY 4.0.
 */
class GravityExperience extends DemoExperience
{
    use BuildsInteractiveCourse;

    protected function packageName(): string
    {
        return 'gravity';
    }

    protected function contentDir(): string
    {
        return 'gravity';
    }

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
                        'sources' => ['Planet and moon data comes from public NASA and JPL tables', 'Every figure shown in the lessons names its source', 'Course text is licensed CC BY 4.0, and the last lesson lists every source'],
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
        return $this->modulesProgram();
    }

    /** Free: no products, events or vouchers. */
    protected function commerce(): void
    {
    }
}
