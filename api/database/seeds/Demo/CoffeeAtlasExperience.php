<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\Art\CoffeeArt;
use Database\Seeders\Demo\Support\H5PPackageBuilder as H5PB;
use Ulams\Cart\Enums\ProductType;
use Ulams\TopicTypeGift\Enum\QuestionTypeEnum as Q;
use Ulams\Vouchers\Enums\CouponTypeEnum;
use App\Models\Webinar;

/**
 * Experience 1: "The Coffee Atlas", editorial, self-paced, slow learning.
 */
class CoffeeAtlasExperience extends DemoExperience
{
    public function key(): string
    {
        return 'coffee';
    }

    protected function palette(): array
    {
        return [
            'background' => CoffeeArt::PAPER,
            'text' => CoffeeArt::INK,
            'accent' => CoffeeArt::ACCENT,
            'secondary' => CoffeeArt::SAGE,
            'onAccent' => '#FFFFFF',
            'displayFont' => 'serif',
        ];
    }

    protected function courseFields(): array
    {
        return [
            'title' => 'The Coffee Atlas — From Seed to Cup',
            'subtitle' => 'A field guide to specialty coffee for curious home baristas',
            'summary' => 'Follow one coffee cherry from a hillside in Huila to your cup. Learn origin, processing, roasting, brewing and tasting — and leave with your own signature recipe.',
            'description' => $this->markdown('course-description'),
            'level' => 'Beginner → Intermediate',
            'language' => 'en',
            'duration' => '~9 h · 6 lessons',
            'hours_to_complete' => null, // lifetime access: a number is a per-learner deadline
            'target_group' => 'Home brewers, café staff in their first year, food writers',
            'public' => false,
            'fields' => [
                'experience' => 'coffee',
                'landing' => [
                    'headline' => 'Learn coffee the slow way.',
                    'subheadline' => 'Six chapters. One cherry. Your best cup.',
                    'cta' => 'Start the free chapter',
                    'testimonials' => [
                        ['quote' => 'I finally understand why my V60 tastes sour.', 'author' => 'Maja, Gdańsk'],
                        ['quote' => 'It reads like a magazine and works like a course. I roasted my first batch after Chapter III.', 'author' => 'Daniel, Wrocław'],
                        ['quote' => 'The cupping session alone changed how our café trains new baristas.', 'author' => 'Aga, café owner, Poznań'],
                    ],
                    'faq' => [
                        ['q' => 'Do I need an espresso machine?', 'a' => 'No. Everything in the course works with a pour-over dripper, a scale and a grinder.'],
                        ['q' => 'How long do I have access?', 'a' => 'For life, including future updates of the chapters.'],
                        ['q' => 'Is the first chapter really free?', 'a' => 'Yes: the welcome film and Chapter I open without an account.'],
                    ],
                    'newsletter' => 'Field notes',
                ],
            ],
        ];
    }

    protected function people(): array
    {
        return [
            'ines' => ['first_name' => 'Inés', 'last_name' => 'Duarte', 'email' => 'ines.duarte@demo.ulams.app', 'role' => 'tutor', 'color' => CoffeeArt::ACCENT],
            'tomasz' => ['first_name' => 'Tomasz', 'last_name' => 'Wierzba', 'email' => 'tomasz.wierzba@demo.ulams.app', 'role' => 'tutor', 'color' => CoffeeArt::SAGE],
            'maja' => ['first_name' => 'Maja', 'last_name' => 'Nowicka', 'email' => 'maja.nowicka@demo.ulams.app', 'role' => 'student', 'color' => '#8A5A33'],
        ];
    }

    protected function categories(): array
    {
        return [
            ['parent' => 'Food & Drink', 'name' => 'Coffee'],
            ['parent' => 'Food & Drink', 'name' => 'Home barista'],
        ];
    }

    protected function tags(): array
    {
        return ['specialty coffee', 'brewing', 'roasting', 'cupping', 'self-paced'];
    }

    protected function courseMedia(): array
    {
        $image = $this->assets->image('course-image.png', fn () => CoffeeArt::coverImage('A field guide · six chapters', 'The Coffee Atlas — From Seed to Cup', 'A field guide to specialty coffee for curious home baristas'));
        $poster = $this->assets->image('course-poster.png', fn () => CoffeeArt::coverImage('Watch the trailer', 'Learn coffee the slow way.', 'Six chapters. One cherry. Your best cup.', 1280, 720));

        return ['image' => $image, 'poster' => $poster, 'teaser' => $this->welcomeFilm()];
    }

    protected function certificateName(): string
    {
        return 'Certified Home Barista — The Coffee Atlas';
    }

    // ------------------------------------------------------------- media

    private function soundtrack(string $name, int $seconds): string
    {
        // A minor pad with slow swell, warm and quiet
        return $this->assets->audio($name, $seconds, '0.05*sin(2*PI*220*t)*(1+0.3*sin(2*PI*0.1*t))+0.04*sin(2*PI*261.63*t)+0.035*sin(2*PI*329.63*t)*(1+0.3*sin(2*PI*0.13*t))+0.02*sin(2*PI*110*t)', 'lowpass=f=3000');
    }

    private function welcomeFilm(): string
    {
        $frames = [];
        $slides = [
            ['A film · 4 minutes', 'Welcome to the Atlas', 'One cherry, six chapters, your best cup.', 'branch'],
            ['Dawn · Huila, Colombia · 1,750 m', 'The harvest starts at five', 'Pickers climb the slopes before the sun burns off the mist. Only the ripest cherries go into the basket.', 'hills'],
            ['Your tutor', 'Inés Duarte, Q-grader', 'Grew up on a farm near Pitalito. Judges cupping competitions from Colombia to Rwanda.', 'plain'],
            ['Your tutor', 'Tomasz Wierzba, roaster', 'Twelve years at the roaster in Kraków. He will review your recipe card.', 'plain'],
            ['The journey', 'Origins · The farm · Roasting · Brewing · Tasting · Your signature cup', 'Read, watch and listen at your own pace.', 'plain'],
            ['Chapter I', 'Let\'s begin with the map.', '', 'dark'],
        ];
        foreach ($slides as $i => [$kicker, $title, $body, $variant]) {
            $frames[] = $this->assets->image("welcome-$i.png", fn () => CoffeeArt::filmFrame($kicker, $title, $body, $variant));
        }

        return $this->assets->video('welcome-to-the-atlas.mp4', $frames, 6, $this->soundtrack('welcome-pad.mp3', 32));
    }

    /** @return array{0: string, 1: array<int, array{time: int, title: string}>} */
    private function pourOverFilm(): array
    {
        $chapters = [
            ['Masterclass', 'The V60 pour-over', '15 g coffee · 250 g water · 93 °C · 1:16.7'],
            ['Chapter 1 · 0:00', 'Rinse and preheat', 'Rinse the paper with hot water to remove paper taste and warm the brewer. Discard the water.'],
            ['Chapter 2 · 1:10', 'Grind 15 g, medium-fine', 'Like coarse sand. Level the bed with a gentle shake and make a small dimple in the middle.'],
            ['Chapter 3 · 2:40', 'Bloom: 45 g for 45 s', 'Pour three times the coffee weight, swirl, and let the CO₂ escape. Fresh coffee bubbles.'],
            ['Chapter 4 · 4:30', 'Main pours to 250 g', 'Pour in slow spirals to 150 g by 1:15, then to 250 g by 1:45. Keep the water level steady.'],
            ['Chapter 5 · 8:20', 'Drawdown at 3:00–3:30', 'A flat bed means even extraction. Fast drawdown: grind finer. Slow: grind coarser.'],
            ['Chapter 6 · 11:00', 'Taste and adjust', 'Sour and thin: finer. Bitter and dry: coarser. Change one thing at a time.'],
        ];
        $frames = [];
        $markers = [];
        foreach ($chapters as $i => [$kicker, $title, $body]) {
            $frames[] = $this->assets->image("pourover-$i.png", fn () => CoffeeArt::filmFrame($kicker, $title, $body, $i === 0 ? 'branch' : 'plain'));
            if ($i > 0) {
                $markers[] = ['time' => $i * 5, 'title' => $title];
            }
        }
        $video = $this->assets->video('pour-over-masterclass.mp4', $frames, 6, $this->soundtrack('pourover-pad.mp3', 38));

        return [$video, $markers];
    }

    private function farmPodcast(): string
    {
        // dawn ambience: wind noise, two birds, distant stream
        $expr = '0.03*(random(0)*2-1)'
            . '+0.18*sin(2*PI*(2800+900*sin(2*PI*13*t))*t)*between(mod(t,3.7),0,0.22)'
            . '+0.12*sin(2*PI*(4200-1500*mod(t,0.12)/0.12)*t)*between(mod(t+1.3,5.1),0,0.36)'
            . '+0.06*sin(2*PI*(1800+300*sin(2*PI*7*t))*t)*between(mod(t+2.2,9.4),0,0.5)';

        return $this->assets->audio('voices-from-the-farm.mp3', 75, $expr, 'lowpass=f=7000,aecho=0.6:0.5:180:0.25');
    }

    // ----------------------------------------------------------- program

    protected function program(): array
    {
        return [
            [
                'title' => 'I. Origins',
                'summary' => 'Where coffee grows, which plants it comes from, and why the mountain matters.',
                'duration' => '1 h 20 min',
                'topics' => [
                    [
                        'type' => 'video', 'title' => 'Welcome to the Atlas', 'preview' => true, 'duration' => '4 min',
                        'introduction' => 'A short film from a dawn harvest in Huila. Inés and Tomasz introduce the journey.',
                        'summary' => 'Meet your tutors and the cherry you will follow for six chapters.',
                        'description' => "**Transcript**\n\nINÉS: It is five in the morning in Huila, and the pickers are already on the slope. Only the deep red cherries go into the basket: a green one would make your cup taste like grass.\n\nTOMASZ: Six weeks from now, the seeds of these cherries will be in my roaster in Kraków. In this course we follow one of them all the way, and you will learn what happens at every step.\n\nINÉS: Six chapters: origins, the farm, roasting, brewing, tasting, and your own signature cup. Take your time. Coffee is slow.",
                        'make' => fn () => ['fields' => [], 'files' => [
                            'value' => $this->welcomeFilm(),
                            'poster' => $this->assets->image('welcome-1.png', fn () => CoffeeArt::filmFrame('Dawn · Huila, Colombia · 1,750 m', 'The harvest starts at five', '', 'hills')),
                        ]],
                    ],
                    [
                        'type' => 'image', 'title' => 'The coffee belt', 'duration' => '10 min',
                        'introduction' => 'An illustrated map of where coffee grows, with the altitude bands of fifteen classic origins.',
                        'summary' => 'Coffee grows between roughly 25° N and 30° S. Arabica likes the mountains (1,000–2,400 m), Robusta the hot lowlands.',
                        'make' => fn () => ['fields' => [], 'files' => ['value' => $this->assets->image('coffee-belt.png', fn () => CoffeeArt::beltMap())]],
                    ],
                    [
                        'type' => 'richtext', 'title' => 'Arabica vs Robusta', 'duration' => '25 min',
                        'introduction' => 'A long read on the two species behind almost every cup.',
                        'summary' => 'Arabica: high altitude, more sugar and aroma. Robusta: lowlands, more caffeine, heavier body.',
                        'make' => fn () => ['fields' => ['value' => $this->markdown('arabica-vs-robusta')], 'files' => []],
                    ],
                ],
            ],
            [
                'title' => 'II. The farm',
                'summary' => 'A producer\'s voice, the anatomy of a cherry and the processing methods that shape flavour.',
                'duration' => '1 h 30 min',
                'topics' => [
                    [
                        'type' => 'audio', 'title' => 'Voices from the farm', 'duration' => '12 min',
                        'introduction' => 'A podcast recorded at dawn on a family farm in Huila, with birdsong in the background.',
                        'summary' => 'Don Álvaro on picking, weather, prices and why he still washes most of his coffee.',
                        'description' => "**Episode notes**\n\n- 00:00 Dawn on the farm: the sound of the slope\n- 01:30 How pickers judge ripeness\n- 04:10 Afternoon rain and the risk of natural drying\n- 07:45 Selling to exporters vs. direct trade\n- 10:20 What he wants home baristas to know\n\n*The ambience track is a soundscape; the interview is summarised in the notes above.*",
                        'make' => fn () => ['fields' => [], 'files' => ['value' => $this->farmPodcast()]],
                    ],
                    [
                        'type' => 'h5p', 'title' => 'Anatomy of a cherry', 'duration' => '10 min',
                        'introduction' => 'Tap the layers of a ripe cherry: skin, pulp, mucilage, parchment, silver skin and bean.',
                        'make' => fn () => $this->cherryHotspots(),
                    ],
                    [
                        'type' => 'richtext', 'title' => 'Processing methods', 'duration' => '30 min',
                        'introduction' => 'Washed, natural, honey and anaerobic: what each one does to the cup.',
                        'summary' => 'Washed coffees are clean and bright, naturals fruity and heavy, honeys sweet and round, anaerobics loud and experimental.',
                        'make' => fn () => ['fields' => ['value' => $this->markdown('processing-methods', [
                            'processing-washed.png' => $this->assets->image('processing-washed.png', fn () => CoffeeArt::processing('washed')),
                            'processing-natural.png' => $this->assets->image('processing-natural.png', fn () => CoffeeArt::processing('natural')),
                            'processing-honey.png' => $this->assets->image('processing-honey.png', fn () => CoffeeArt::processing('honey')),
                            'processing-anaerobic.png' => $this->assets->image('processing-anaerobic.png', fn () => CoffeeArt::processing('anaerobic')),
                        ])], 'files' => []],
                    ],
                    [
                        'type' => 'oembed', 'title' => 'Visit the cooperative', 'duration' => '8 min',
                        'introduction' => 'A 360° walk through a Colombian coffee farm. Drag the video to look around.',
                        'summary' => 'Notice the shade trees, the slope and the drying area next to the wet mill.',
                        'fields' => ['value' => 'https://www.youtube.com/watch?v=aSdVF_Xfi0w'],
                    ],
                ],
            ],
            [
                'title' => 'III. Roasting',
                'summary' => 'Read a roast curve, keep a logbook and roast your first virtual batch.',
                'duration' => '1 h 40 min',
                'topics' => [
                    [
                        'type' => 'image', 'title' => 'The roast curve', 'duration' => '15 min',
                        'introduction' => 'An annotated roast curve of a washed Caturra from Huila.',
                        'summary' => 'Charge, turning point, dry end, first crack and drop. Development time is the share of the roast after first crack: 21 % here.',
                        'make' => fn () => ['fields' => [], 'files' => ['value' => $this->assets->image('roast-curve.png', fn () => CoffeeArt::roastCurve())]],
                    ],
                    [
                        'type' => 'pdf', 'title' => 'Roaster\'s logbook', 'duration' => '10 min',
                        'introduction' => 'An 8-page printable roast log: lot record, batch sheets, cupping scores and notes.',
                        'summary' => 'Print it double-sided and log every batch from now on.',
                        'make' => fn () => ['fields' => [], 'files' => ['value' => $this->logbook()]],
                        'resources' => [fn () => [$this->logbook(), 'roasters-logbook.pdf']],
                    ],
                    [
                        'type' => 'scorm', 'title' => 'Roast simulator', 'duration' => '30 min',
                        'introduction' => 'Pick a charge temperature and a heat setting, watch the beans change colour and drop the batch at the right moment.',
                        'summary' => 'Aim for a drop at 205–212 °C with 16–25 % development. Your score is saved to your progress.',
                        'make' => fn () => $this->scorm('roast-simulator.zip', 'roast-simulator'),
                    ],
                    [
                        'type' => 'liascript', 'title' => 'Cupping vocabulary', 'duration' => '10 min',
                        'introduction' => 'An interactive LiaScript lesson: the words cuppers use, with a short check at the end.',
                        'summary' => 'Fragrance, aroma, acidity, body and finish, in the order you meet them at the table.',
                        'make' => fn () => $this->liascript('Cupping vocabulary', <<<'MD'
# Cupping vocabulary

At a cupping table everyone describes the same coffee with the same words. Here they are, in the
order you meet them.

## Fragrance and aroma

**Fragrance** is the smell of the dry grounds. **Aroma** is the smell after the hot water goes in
and the crust breaks. Write down both: they often differ.

## Acidity, body, finish

- **Acidity**: the bright, mouth-watering part (citrus, apple, berry).
- **Body**: weight and texture on the tongue (tea-like to syrupy).
- **Finish**: what stays after you swallow, and how long it stays.

## Check yourself

What do you smell right after breaking the crust?

- [( )] Fragrance
- [(X)] Aroma
- [( )] Finish

Which word describes how heavy a coffee feels?

[[body]]
MD),
                    ],
                ],
            ],
            [
                'title' => 'IV. Brewing',
                'summary' => 'The maths of a good cup and the technique to pour it.',
                'duration' => '1 h 50 min',
                'topics' => [
                    [
                        'type' => 'richtext', 'title' => 'Ratios & extraction', 'duration' => '30 min',
                        'introduction' => 'Why a cup tastes sour or bitter, and how to fix it with numbers.',
                        'summary' => 'Balanced cups extract 18–22 % of the dose. Start at 1:16 and adjust the grind, not the ratio, to fix extraction.',
                        'make' => fn () => ['fields' => ['value' => $this->markdown('ratios-and-extraction')], 'files' => []],
                    ],
                    [
                        'type' => 'video', 'title' => 'Pour-over masterclass', 'duration' => '14 min',
                        'introduction' => 'Tomasz brews a V60 step by step. Use the chapter markers to jump between steps.',
                        'summary' => 'Rinse, grind, bloom, pour, drawdown, taste and adjust.',
                        'make' => function () {
                            [$video, $markers] = $this->pourOverFilm();

                            return [
                                'fields' => ['json' => ['chapters' => $markers]],
                                'files' => ['value' => $video, 'poster' => $this->assets->image('pourover-0.png', fn () => CoffeeArt::filmFrame('Masterclass', 'The V60 pour-over', '', 'branch'))],
                            ];
                        },
                        'description' => "**Chapters**\n\n1. Rinse and preheat\n2. Grind 15 g, medium-fine\n3. Bloom: 45 g for 45 s\n4. Main pours to 250 g\n5. Drawdown at 3:00–3:30\n6. Taste and adjust\n\n**Recipe:** 15 g coffee, 250 g water at 93 °C, total time 3:00–3:30.",
                    ],
                    [
                        'type' => 'h5p', 'title' => 'Grind size matching', 'duration' => '10 min',
                        'introduction' => 'Drag each grind size to the brewer it suits.',
                        'make' => fn () => $this->grindDragAndDrop(),
                    ],
                ],
            ],
            [
                'title' => 'V. Tasting',
                'summary' => 'Describe what you taste, cup like a professional and check what you learned.',
                'duration' => '1 h 50 min',
                'topics' => [
                    [
                        'type' => 'h5p', 'title' => 'The flavour wheel', 'duration' => '15 min',
                        'introduction' => 'Flip the cards to learn the families of the SCA Coffee Taster\'s Flavor Wheel.',
                        'make' => fn () => $this->flavourCards(),
                    ],
                    [
                        'type' => 'cmi5', 'title' => 'Cupping at home', 'duration' => '45 min',
                        'introduction' => 'A guided cupping session with timers and a scoring form. Your scores are recorded in your learning record.',
                        'summary' => 'Opens the guided cupping session. You need two coffees, four cups and a spoon.',
                        'make' => fn () => $this->cmi5('cupping-session.zip', 'cupping-session'),
                    ],
                    [
                        'type' => 'gift', 'title' => 'Tasting check', 'duration' => '15 min',
                        'introduction' => 'Eight questions, one of each kind. You have three attempts and 20 minutes.',
                        'fields' => [
                            'value' => 'Check what you learned about processing, roasting, brewing and tasting. One question of each type; the essay is reviewed by your tutor.',
                            'max_attempts' => 3,
                            'max_execution_time' => 20,
                            'min_pass_score' => 5,
                            'counts_to_grade' => true,
                            'weight' => 30,
                            'randomize_order' => false,
                        ],
                        'questions' => $this->tastingCheck(),
                    ],
                ],
            ],
            [
                'title' => 'VI. Your signature cup',
                'summary' => 'Design, test and write down a recipe that is yours.',
                'duration' => '1 h 30 min',
                'topics' => [
                    [
                        'type' => 'project', 'title' => 'Design your recipe', 'duration' => '75 min',
                        'introduction' => 'Brew three variations, photograph them and submit your recipe card. Your tutor replies with feedback.',
                        'make' => fn () => ['fields' => [
                            'value' => $this->markdown('project-brief'),
                            'notify_users' => $this->tutorIds(),
                            'counts_to_grade' => true,
                            'weight' => 70,
                            'max_score' => 10,
                        ], 'files' => []],
                        'resources' => [fn () => [$this->assets->pdf('recipe-card.pdf', CoffeeArt::recipeCardHtml()), 'signature-recipe-card.pdf']],
                    ],
                    [
                        'type' => 'richtext', 'title' => 'Final reflections', 'can_skip' => true, 'duration' => '15 min',
                        'introduction' => 'A reading list and a coffee glossary to keep.',
                        'make' => fn () => ['fields' => ['value' => $this->markdown('final-reflections')], 'files' => []],
                    ],
                ],
            ],
        ];
    }

    private function logbook(): string
    {
        return $this->assets->pdf('roasters-logbook.pdf', CoffeeArt::logbookHtml());
    }

    /** @return array<string, mixed> */
    private function cherryHotspots(): array
    {
        $image = $this->assets->image('cherry-anatomy.png', fn () => CoffeeArt::cherryAnatomy());
        $hotspots = array_map(fn ($h) => [
            'position' => ['x' => $h[0], 'y' => $h[1]],
            'alwaysFullscreen' => false,
            'header' => $h[2],
            'content' => [H5PB::text($h[3], 'H5P.Text 1.1', $h[2])],
        ], CoffeeArt::cherryHotspots());

        return $this->h5p('image-hotspots', 'Anatomy of a coffee cherry', [
            'image' => H5PB::imageInfo($image, 'images/cherry-anatomy.png'),
            'hotspots' => $hotspots,
            'color' => CoffeeArt::ACCENT,
            'iconType' => 'icon',
            'icon' => 'plus',
        ], ['images/cherry-anatomy.png' => $image]);
    }

    /** @return array<string, mixed> */
    private function grindDragAndDrop(): array
    {
        $board = $this->assets->image('grind-board.png', fn () => CoffeeArt::grindBoard());
        $grinds = [
            ['Medium-fine', 0],
            ['Coarse', 1],
            ['Fine', 2],
            ['Extra coarse', 3],
            ['Powder (Turkish)', null],
        ];
        $order = [2, 0, 4, 3, 1]; // shuffled positions in the tray
        $elements = [];
        foreach ($grinds as $i => [$label]) {
            $slot = array_search($i, $order, true);
            $elements[] = [
                'type' => H5PB::text('<p>' . $label . '</p>', 'H5P.AdvancedText 1.1', $label),
                'x' => 3 + $slot * 19.4,
                'y' => 82,
                'width' => 9,
                'height' => 1.75,
                'dropZones' => ['0', '1', '2', '3'],
                'backgroundOpacity' => 100,
                'multiple' => false,
            ];
        }
        $zones = [];
        foreach (['V60', 'French press', 'Espresso', 'Cold brew'] as $z => $name) {
            $correct = array_keys(array_filter($grinds, fn ($g) => $g[1] === $z));
            $zones[] = [
                'x' => (20 + $z * 195 + 10) / 8,
                'y' => 54,
                'width' => 10,
                'height' => 3,
                'correctElements' => array_map('strval', $correct),
                'showLabel' => false,
                'backgroundOpacity' => 0,
                'label' => '<div>' . $name . '</div>',
                'single' => true,
                'autoAlign' => true,
                'tipsAndFeedback' => [
                    'tip' => ['V60' => 'Like coarse sand.', 'French press' => 'Like sea salt: four minutes of contact.', 'Espresso' => 'Like fine table salt: 25–30 seconds under pressure.', 'Cold brew' => 'Like cracked pepper: it steeps for 16 hours.'][$name],
                    'feedbackOnCorrect' => 'Yes.',
                    'feedbackOnIncorrect' => 'Think about contact time: the longer the brew, the coarser the grind.',
                ],
            ];
        }

        return $this->h5p('drag-and-drop', 'Grind size matching', [
            'question' => [
                'settings' => ['size' => ['width' => 800, 'height' => 500], 'background' => H5PB::imageInfo($board, 'images/grind-board.png')],
                'task' => ['elements' => $elements, 'dropZones' => $zones],
            ],
            'overallFeedback' => [['from' => 0, 'to' => 100, 'feedback' => 'You matched @score of @total. Remember: longer contact time, coarser grind.']],
        ], ['images/grind-board.png' => $board]);
    }

    /** @return array<string, mixed> */
    private function flavourCards(): array
    {
        $cards = [
            ['Fruity → Berry', 'Blackberry, raspberry, blueberry, strawberry. Typical of natural Ethiopians and Kenyan SL28.'],
            ['Fruity → Citrus', 'Grapefruit, orange, lemon, lime. Bright, clean acidity of washed high-altitude coffees.'],
            ['Fruity → Dried fruit', 'Raisin, prune. Ripe naturals and long fermentations.'],
            ['Floral', 'Jasmine, rose, chamomile, black tea. The signature of Gesha and Yirgacheffe.'],
            ['Sweet', 'Brown sugar, molasses, maple syrup, honey, vanilla. Caramelised sugars from a well-developed roast.'],
            ['Nutty / Cocoa', 'Almond, hazelnut, peanut, chocolate, dark chocolate. Brazil, Guatemala and many espresso blends.'],
            ['Spices', 'Clove, cinnamon, nutmeg, anise, black pepper. Sumatra, some Central Americans, anaerobic lots.'],
            ['Roasted', 'Cereal, malt, tobacco, pipe smoke. Fine in a dark roast, a defect when it hides everything else.'],
            ['Sour / Fermented', 'Winey, whisky, overripe. Pleasant in small doses; too much points to over-fermentation.'],
            ['Green / Vegetative', 'Fresh herbs, peapod, raw. Usually under-ripe cherries or an under-developed roast.'],
        ];

        return $this->h5p('dialog-cards', 'The flavour wheel', [
            'title' => '<p>The SCA Coffee Taster\'s Flavor Wheel</p>',
            'description' => '<p>Read the family on the front, guess what it tastes like, then turn the card. Start in the centre of the wheel and move outwards to more specific notes.</p>',
            'dialogs' => array_map(fn ($c) => [
                'text' => '<p style="text-align:center">' . $c[0] . '</p>',
                'answer' => '<p style="text-align:center">' . $c[1] . '</p>',
                'tips' => (object) [],
            ], $cards),
            'mode' => 'normal',
        ]);
    }

    /** @return array<int, array{type: string, gift: string, score?: int}> */
    private function tastingCheck(): array
    {
        return [
            ['type' => Q::MULTIPLE_CHOICE, 'gift' => '::Fruitiest process:: Which process usually gives the fruitiest cup? {=Natural ~Washed ~Wet-hulled ~Decaf}'],
            ['type' => Q::MULTIPLE_CHOICE_WITH_MULTIPLE_RIGHT_ANSWERS, 'gift' => '::Extraction variables:: Which variables change extraction? {~%33.33333%Grind size ~%33.33333%Water temperature ~%33.33334%Brew time ~%-100%Cup colour}'],
            ['type' => Q::TRUE_FALSE, 'gift' => '::Dark roast caffeine:: Darker roasts contain more caffeine by weight. {F}'],
            ['type' => Q::SHORT_ANSWERS, 'gift' => '::First crack:: The moment beans audibly pop during roasting is called… {=first crack =First crack =first-crack}'],
            ['type' => Q::MATCHING, 'gift' => '::Grind and brewer:: Match each brew method with its grind size. {=V60 -> medium-fine =French press -> coarse =Espresso -> fine =Cold brew -> extra coarse}'],
            ['type' => Q::NUMERICAL_QUESTION, 'gift' => '::Ratio maths:: Using a 1\:16 ratio, how many grams of water do you need for 18 g of coffee? {#288:2}'],
            ['type' => Q::ESSAY, 'gift' => '::Favourite cup:: Describe your favourite cup this week using three descriptors from the flavour wheel. {}', 'score' => 2],
            ['type' => Q::DESCRIPTION, 'gift' => '::Pause:: Take a sip of water before the next section.', 'score' => 0],
        ];
    }

    // ---------------------------------------------------------- commerce

    protected function commerce(): void
    {
        $course = $this->product('The Coffee Atlas — From Seed to Cup', [
            'type' => ProductType::SINGLE,
            'price' => 8900,
            'description' => 'Six chapters, a roast simulator, a guided cupping session and a reviewed final project. Lifetime access.',
            'productables' => [$this->courseProductable()],
            'limit_per_user' => 1,
            'tags' => ['coffee'],
        ]);

        $webinar = $this->webinar('Live cupping with Inés', [
            'description' => '<p>Cup four Colombian lots live with Inés Duarte: two washed, one honey, one anaerobic. Order the tasting kit at least a week before, or follow along with any two coffees you have at home.</p>',
            'short_desc' => 'Live cupping session with Q-grader Inés Duarte.',
            'agenda' => '<ol><li>18:00 Setting up the table</li><li>18:15 Dry fragrance and wet aroma</li><li>18:35 Tasting and scoring</li><li>19:15 Q&amp;A</li></ol>',
            'active_from' => '2026-11-14 17:00:00',
            'active_to' => '2026-11-14 18:30:00',
            'duration' => '1.5',
            'trainers' => [$this->user('ines')->getKey()],
            'tags' => ['coffee', 'cupping'],
            'image' => $this->assets->image('webinar-cupping.png', fn () => CoffeeArt::filmFrame('Live · 14 Nov, 18:00 CET', 'Live cupping with Inés', 'Four Colombian lots, one table, your spoon.', 'branch')),
        ]);

        $this->product('Taste Makers', [
            'type' => ProductType::BUNDLE,
            'price' => 10900,
            'price_old' => 12900,
            'description' => 'The Coffee Atlas course plus the live cupping masterclass with Inés Duarte.',
            'productables' => [$this->courseProductable(), ['id' => $webinar->getKey(), 'class' => Webinar::class, 'quantity' => 1]],
            'limit_per_user' => 1,
        ]);

        $this->stationaryEvent('Home cupping table at the roastery', [
            'description' => '<p>Join Tomasz at the roastery in Kraków for a Saturday morning cupping: six coffees from three continents, roasted the week before, and a tour of the 15 kg drum roaster.</p>',
            'short_desc' => 'Saturday cupping and roastery tour with Tomasz Wierzba.',
            'started_at' => '2026-11-28 10:00:00',
            'finished_at' => '2026-11-28 13:00:00',
            'max_participants' => 12,
            'place' => 'Wierzba Roasters, ul. Józefa 12, Kraków',
            'program' => 'Cupping, roastery tour, Q&A',
            'authors' => [$this->user('tomasz')->getKey()],
            'image' => $this->assets->image('event-roastery.png', fn () => CoffeeArt::filmFrame('Kraków · 28 Nov', 'Home cupping table at the roastery', 'Six coffees, one drum roaster, Saturday morning.', 'hills')),
        ]);

        $this->coupon('FIELDNOTES10', [
            'name' => 'Field notes newsletter: 10 % off',
            'type' => CouponTypeEnum::PRODUCT_PERCENT,
            'amount' => 10,
            'limit_per_user' => 1,
            'active_to' => '2027-03-31 23:59:59',
            'included_products' => [$course->getKey()],
        ]);

        $this->grantAccess($course, 'maja');
    }
}
