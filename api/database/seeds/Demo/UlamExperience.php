<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\Art\UlamArt;
use Database\Seeders\Demo\Support\BuildsInteractiveCourse;
use Database\Seeders\Demo\Support\ContentPackages;

/**
 * Demo academy "The Scottish Book": a free course on Stanisław Ulam and the Lwów School of Mathematics, with five
 * small live interactives (demo-content/ulam: spiral, monte-carlo, automaton, scottish-book, lwow-map). A welcome
 * lesson (it ends with the spiral, which the landing hero plays), eight modules (Lwów, the Lwów School and the
 * café, America and the war, Monte Carlo, the Teller-Ulam design, patterns, Fermi-Pasta-Ulam-Tsingou, legacy and
 * the name), a 14-question final test with the certificate and a "Sources and licence" lesson. The text is in
 * Demo/content/ulam/modules/, CC BY 4.0, and rests on the fact sheet demo-content/ulam/facts.json. The four
 * photographs are in Demo/assets/ulam/images/. No account carries Ulam's name.
 */
class UlamExperience extends DemoExperience
{
    use BuildsInteractiveCourse;

    protected function packageName(): string
    {
        return 'ulam-spiral';
    }

    protected function packageNames(): array
    {
        return ContentPackages::ULAM;
    }

    protected function contentDir(): string
    {
        return 'ulam';
    }

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
                        ['q' => 'Is the course free?', 'a' => 'Yes. Every lesson, quiz, the final test and the certificate are free.'],
                        ['q' => 'Do I need advanced mathematics?', 'a' => 'No. The interactives let you meet each idea by doing it first, and the text explains it in plain language.'],
                        ['q' => 'Where do the facts come from?', 'a' => 'From published sources, named in every lesson and listed together in the last one. Where careful sources disagree, the lesson says so.'],
                        ['q' => 'Why is the product called ulams?', 'a' => 'ULAMS is named in tribute to the mathematician Stanisław Ulam (1909–1984), the subject of this course. The project is not affiliated with or endorsed by his estate, Los Alamos National Laboratory or any institution associated with him.'],
                    ],
                    'lists' => [
                        'sources' => ['Every statement of a lesson is checked against a published source and cited', 'Where sources disagree the lesson says so, and the quizzes never ask about the disputed point', 'The last lesson lists every source and credits every photograph', 'Course text is licensed CC BY 4.0'],
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
        return $this->modulesProgram();
    }

    /** Free: no products, events or vouchers. */
    protected function commerce(): void
    {
    }
}
