<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\Art\UlamArt;

/**
 * Demo academy "The Scottish Book": a free course on Stanisław Ulam and the Lwów School of
 * Mathematics, with live mathematical interactives. This is the placeholder course the tenant
 * and its landing page run on; the notebooks, the facts (checked against sources first) and
 * the interactives arrive with the course content. No account carries Ulam's name.
 */
class UlamExperience extends DemoExperience
{
    public function key(): string
    {
        return 'ulam';
    }

    protected function palette(): array
    {
        return [
            'background' => UlamArt::PAPER,
            'text' => UlamArt::INK,
            'accent' => UlamArt::BLUE,
            'secondary' => UlamArt::RED,
            'onAccent' => '#FFFFFF',
            'displayFont' => 'serif',
        ];
    }

    protected function courseFields(): array
    {
        return [
            'title' => 'Stanisław Ulam and the Lwów School of Mathematics',
            'subtitle' => 'Problems from the Scottish Book, worked with live interactives',
            'summary' => 'Follow the mathematicians of the Lwów School through the problems they set each other, and try the ideas yourself in live interactives.',
            'description' => $this->markdown('course-description'),
            'level' => 'Beginner',
            'language' => 'en',
            'duration' => 'Self-paced',
            'hours_to_complete' => null, // lifetime access: a number is a per-learner deadline
            'target_group' => 'Students and curious readers who enjoy mathematics and its history',
            'public' => true,
            'fields' => [
                'experience' => 'ulam',
                'landing' => [
                    'headline' => 'Open the Scottish Book',
                    'subheadline' => 'Mathematics and history, with live interactives you can play with.',
                    'cta' => 'Start the course — free',
                    'faq' => [
                        ['q' => 'Is the course free?', 'a' => 'Yes. Every notebook, quiz and the certificate are free.'],
                        ['q' => 'Do I need advanced mathematics?', 'a' => 'No. The interactives let you meet each idea by doing it first, and the text explains it in plain language.'],
                        ['q' => 'Why is the product called ulams?', 'a' => 'The platform takes its name from the mathematician Stanisław Ulam, who is the subject of this course. The platform itself is a general learning system.'],
                    ],
                    'lists' => [
                        'sources' => ['Historical statements are checked against published sources before they are used', 'Every fact in a lesson is cited'],
                        'features' => ['Play with the interactive', 'Read the story behind it', 'Check yourself with a quiz'],
                    ],
                ],
            ],
        ];
    }

    protected function people(): array
    {
        return [
            'elena' => ['first_name' => 'Elena', 'last_name' => 'Marsh', 'email' => 'elena.marsh@demo.ulams.app', 'role' => 'tutor', 'color' => UlamArt::BLUE],
        ];
    }

    protected function categories(): array
    {
        return [
            ['parent' => 'Science', 'name' => 'Mathematics'],
            ['parent' => 'Society', 'name' => 'History'],
        ];
    }

    protected function tags(): array
    {
        return ['mathematics', 'history', 'ulam', 'scottish book', 'free'];
    }

    protected function courseMedia(): array
    {
        return [
            'image' => $this->assets->image('course-image.png', fn () => UlamArt::cover('Free · mathematics and history', 'The Scottish Book', 'Problems, and the people who set them')),
            'poster' => $this->assets->image('course-poster.png', fn () => UlamArt::cover('Free · mathematics and history', 'The Scottish Book', 'Problems, and the people who set them', 1280, 720)),
            'teaser' => null,
        ];
    }

    protected function certificateName(): string
    {
        return 'The Scottish Book — certificate';
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
                        'type' => 'richtext', 'title' => 'Welcome to the Scottish Book', 'preview' => true, 'duration' => '5 min',
                        'introduction' => 'How the course works and what comes next.',
                        'summary' => 'Interactives, short stories and a source for every fact.',
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
