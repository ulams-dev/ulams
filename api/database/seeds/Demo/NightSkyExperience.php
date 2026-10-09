<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\Art\NightSkyArt;
use Database\Seeders\Demo\Support\H5PPackageBuilder as H5PB;
use Ulams\Cart\Enums\PeriodEnum;
use Ulams\Cart\Enums\ProductType;
use Ulams\TopicTypeGift\Enum\QuestionTypeEnum as Q;
use Ulams\Vouchers\Enums\CouponTypeEnum;

/**
 * Experience 3: "Night Sky Explorers", playful, gamified, mobile-first,
 * for kids aged 10–14.
 */
class NightSkyExperience extends DemoExperience
{
    public function key(): string
    {
        return 'nightsky';
    }

    protected function palette(): array
    {
        return [
            'background' => NightSkyArt::NIGHT,
            'text' => NightSkyArt::WHITE,
            'accent' => NightSkyArt::YELLOW,
            'secondary' => NightSkyArt::MINT,
            'onAccent' => NightSkyArt::NIGHT,
            'displayFont' => 'sans',
        ];
    }

    protected function courseFields(): array
    {
        return [
            'title' => 'Night Sky Explorers',
            'subtitle' => 'A 7-mission journey through planets, stars and galaxies',
            'summary' => 'Build your own star map mission by mission. Spot constellations, explore planets, and earn your Junior Astronomer badge.',
            'description' => $this->markdown('course-description'),
            'level' => 'Beginner (ages 10–14)',
            'language' => 'en',
            'duration' => '7 missions · 10–15 min each',
            'hours_to_complete' => 3,
            'target_group' => 'Curious kids, homeschool families, teachers running a club',
            'public' => false,
            'fields' => [
                'experience' => 'nightsky',
                'audio_languages' => ['en', 'pl'],
                'badges' => ['Moon Watcher', 'Planet Hopper', 'Star Finder', 'Junior Astronomer'],
                'landing' => [
                    'headline' => 'Your adventure to the stars starts tonight',
                    'cta' => 'Start mission 1 — free',
                    'for_parents' => ['progress emails', 'safe and ad-free', '10–15 minute missions', 'family subscription €6/month'],
                    'for_teachers' => ['classroom package', 'printable worksheets', 'teacher\'s guide'],
                    'testimonials' => [
                        ['quote' => 'I found Cassiopeia from my balcony!', 'author' => 'Zosia, 11'],
                        ['quote' => 'The Moon game finally made phases click for him.', 'author' => 'Marta, mum of Kuba (12)'],
                        ['quote' => 'Our astronomy club does one mission every Friday.', 'author' => 'Mr Nowak, teacher, Lublin'],
                    ],
                ],
            ],
        ];
    }

    protected function people(): array
    {
        return [
            'ada' => ['first_name' => 'Ada', 'last_name' => 'Kowalczyk', 'email' => 'ada.kowalczyk@demo.ulams.app', 'role' => 'tutor', 'color' => NightSkyArt::LILAC],
            'orbi' => ['first_name' => 'Orbi', 'last_name' => 'the Robot', 'email' => 'orbi@demo.ulams.app', 'role' => 'tutor', 'color' => NightSkyArt::MINT],
            'zosia' => ['first_name' => 'Zosia', 'last_name' => 'Nowak', 'email' => 'zosia.nowak@demo.ulams.app', 'role' => 'student', 'color' => NightSkyArt::CORAL],
        ];
    }

    protected function categories(): array
    {
        return [
            ['parent' => 'Kids & family', 'name' => 'Astronomy'],
            ['parent' => 'Science', 'name' => 'Space'],
        ];
    }

    protected function tags(): array
    {
        return ['astronomy', 'kids', 'planets', 'constellations', 'family'];
    }

    protected function courseMedia(): array
    {
        return [
            'image' => $this->assets->image('course-image.png', fn () => NightSkyArt::cover('7 missions · ages 10–14', 'Night Sky Explorers', 'A 7-mission journey through planets, stars and galaxies')),
            'poster' => $this->assets->image('course-poster.png', fn () => NightSkyArt::cover('Mission 1 is free', 'Your adventure to the stars starts tonight', 'Build your own star map, mission by mission.', 1280, 720)),
            'teaser' => $this->meetOrbi(),
        ];
    }

    protected function certificateName(): string
    {
        return 'Junior Astronomer — Night Sky Explorers';
    }

    // ------------------------------------------------------------- media

    private function soundtrack(string $name, int $seconds): string
    {
        // bright, bouncy arpeggio in C major
        return $this->assets->audio($name, $seconds, '0.06*sin(2*PI*(261.63*(1+0.25*between(mod(t,1),0.25,0.5)+0.5*between(mod(t,1),0.5,0.75)+1*between(mod(t,1),0.75,1)))*t)*(1-mod(t,0.25)*2)+0.04*sin(2*PI*130.81*t)', 'lowpass=f=4000,aecho=0.6:0.4:250:0.2');
    }

    private function meetOrbi(): string
    {
        $slides = [
            ['Mission 1 · Lift-off', 'Hi, I\'m Orbi!', 'I\'m a robot guide. I have travelled around the Sun 4,000 times. Well… nearly.', 'orbi'],
            ['Your mission', 'Build your own star map', 'Seven missions. Ten minutes each. Lots of stars to collect.', 'orbi'],
            ['Meet Dr. Ada', 'Dr. Ada Kowalczyk, astrophysicist', 'She studies how stars are born. She will reply to your star map!', 'nebula'],
            ['How it works', 'Watch, play, look up', 'Every mission has a video, a game or a challenge you do under the real sky.', 'planet'],
            ['Ready?', '3… 2… 1… lift-off!', '', 'star'],
        ];
        $frames = [];
        foreach ($slides as $i => [$kicker, $title, $body, $scene]) {
            $frames[] = $this->assets->image("orbi-$i.png", fn () => NightSkyArt::filmFrame($kicker, $title, $body, $scene));
        }

        return $this->assets->video('meet-orbi.mp4', $frames, 6, $this->soundtrack('orbi-tune.mp3', 26));
    }

    private function starsAreBorn(): string
    {
        $slides = [
            ['Mission 4 · Stars', 'How stars are born', 'An explainer by Dr. Ada and Orbi.', 'nebula'],
            ['Step 1', 'A giant cloud of gas and dust', 'Stars are born inside nebulas: clouds so big that light takes years to cross them.', 'nebula'],
            ['Step 2', 'Gravity pulls it together', 'A clump of the cloud gets squeezed tighter and tighter, and hotter and hotter.', 'dwarf'],
            ['Step 3', 'The star switches on!', 'At about 10 million °C the centre starts fusing hydrogen into helium. A new star shines.', 'star'],
            ['Step 4', 'Leftovers make planets', 'Dust and gas spinning around the young star clump into planets. That is how our Earth formed!', 'planet'],
        ];
        $frames = [];
        foreach ($slides as $i => [$kicker, $title, $body, $scene]) {
            $frames[] = $this->assets->image("nebula-$i.png", fn () => NightSkyArt::filmFrame($kicker, $title, $body, $scene));
        }

        return $this->assets->video('how-stars-are-born.mp4', $frames, 6, $this->soundtrack('nebula-tune.mp3', 26));
    }

    private function saturnSonification(): string
    {
        $expr = '0.09*sin(2*PI*(180+140*sin(2*PI*0.05*t))*t)'
            . '+0.05*sin(2*PI*330*t)*(0.5+0.5*sin(2*PI*0.3*t))'
            . '+0.07*sin(2*PI*(700+500*mod(t,2.5)/2.5)*t)*between(mod(t,2.5),0,0.35)'
            . '+0.04*sin(2*PI*1320*t)*between(mod(t+1,5),0,0.08)'
            . '+0.02*(random(0)*2-1)*(0.5+0.5*sin(2*PI*0.08*t))';

        return $this->assets->audio('sounds-of-saturn.mp3', 60, $expr, 'lowpass=f=5000,aecho=0.8:0.7:400|700:0.35|0.2');
    }

    // ----------------------------------------------------------- program

    protected function program(): array
    {
        return [
            [
                'title' => 'Mission 1 · Lift-off',
                'summary' => 'Meet Orbi and find tonight\'s sky. This mission is free.',
                'duration' => '10 min',
                'topics' => [
                    [
                        'type' => 'video', 'title' => 'Meet Orbi', 'preview' => true, 'duration' => '2 min',
                        'introduction' => 'Orbi the robot guide says hello and explains your mission.',
                        'summary' => 'Seven missions, one star map, lots of stars to collect.',
                        'description' => "**Read along**\n\nORBI: Hi, I'm Orbi! I'm a robot guide, and I love the night sky.\n\nORBI: Your mission is to build your very own star map. Seven missions, about ten minutes each.\n\nORBI: My friend Dr. Ada studies how stars are born. She will look at your star map at the end!\n\nORBI: Ready? Three… two… one… lift-off!\n\n*Polish narration: switch the audio language to PL in the player.*",
                        'make' => fn () => ['fields' => [], 'files' => [
                            'value' => $this->meetOrbi(),
                            'poster' => $this->assets->image('orbi-0.png', fn () => NightSkyArt::filmFrame('Mission 1 · Lift-off', 'Hi, I\'m Orbi!', '', 'orbi')),
                        ]],
                    ],
                    [
                        'type' => 'image', 'title' => 'Your sky tonight', 'preview' => true, 'duration' => '8 min',
                        'introduction' => 'A star chart of tonight\'s sky. Hold it above your head with N pointing north.',
                        'summary' => 'Find the Big Dipper low in the north, Cassiopeia high above you and the Moon in the south-east.',
                        'make' => fn () => ['fields' => [], 'files' => ['value' => $this->assets->image('star-chart.png', fn () => NightSkyArt::starChart())]],
                    ],
                ],
            ],
            [
                'title' => 'Mission 2 · Our Moon',
                'summary' => 'Why the Moon changes shape, and a diary to watch it for 30 days.',
                'duration' => '15 min',
                'topics' => [
                    [
                        'type' => 'h5p', 'title' => 'Why does the Moon change?', 'duration' => '8 min',
                        'introduction' => 'Drag each Moon phase to the right place on the Moon\'s path around Earth.',
                        'make' => fn () => $this->moonPhases(),
                    ],
                    [
                        'type' => 'pdf', 'title' => 'Moon diary', 'duration' => '5 min',
                        'introduction' => 'A printable 30-day Moon observation sheet.',
                        'summary' => 'Draw the Moon every evening for 30 days. How long from one full Moon to the next?',
                        'make' => fn () => ['fields' => [], 'files' => ['value' => $this->moonDiary()]],
                        'resources' => [fn () => [$this->moonDiary(), 'moon-diary.pdf']],
                    ],
                ],
            ],
            [
                'title' => 'Mission 3 · Planets',
                'summary' => 'Meet all eight planets, listen to Saturn and fly to Mars.',
                'duration' => '15 min',
                'topics' => [
                    [
                        'type' => 'richtext', 'title' => 'Planet parade', 'duration' => '6 min',
                        'introduction' => 'Short facts and big pictures of all eight planets.',
                        'summary' => 'My Very Excellent Mother Just Served Us Noodles: Mercury, Venus, Earth, Mars, Jupiter, Saturn, Uranus, Neptune.',
                        'make' => fn () => ['fields' => ['value' => $this->markdown('planet-parade', [
                            'planet-mercury.png' => $this->assets->image('planet-mercury.png', fn () => NightSkyArt::planetCard('Mercury', '#B7B0A8', null, '#8F8880', 'The closest planet to the Sun. A year lasts only 88 days!')),
                            'planet-mars.png' => $this->assets->image('planet-mars.png', fn () => NightSkyArt::planetCard('Mars', '#E06A3B', null, '#B84B22', 'Mars is red because its dust is rusty. Robots drive around on it!')),
                            'planet-jupiter.png' => $this->assets->image('planet-jupiter.png', fn () => NightSkyArt::planetCard('Jupiter', '#D9A066', null, '#A86B3C', 'The biggest planet: more than 1,300 Earths would fit inside.')),
                            'planet-saturn.png' => $this->assets->image('planet-saturn.png', fn () => NightSkyArt::planetCard('Saturn', '#E8B25C', '#F3D59A', '#C98B3A', 'Saturn is so light it would float in a giant bathtub!')),
                        ])], 'files' => []],
                    ],
                    [
                        'type' => 'audio', 'title' => 'Sounds of space', 'duration' => '4 min',
                        'introduction' => 'Space is silent, but scientists turn data into sound. Listen to Saturn\'s rings as music!',
                        'summary' => 'Sonification turns measurements (like radio waves or brightness) into notes you can hear.',
                        'description' => "**Read-aloud script**\n\nORBI: Space is silent. There is no air to carry sound.\n\nDR. ADA: But spacecraft like Cassini measured radio waves around Saturn. Scientists turned those measurements into sound. This is called **sonification**.\n\nORBI: The low hum is Saturn's radio waves. The rising whistles are particles zooming past the rings.\n\nDR. ADA: Close your eyes. You are flying between the rings of Saturn, 1.4 billion km from home.\n\n*This track is a sonification-style soundscape made for the course. Polish narration is available on request for classroom packages.*",
                        'make' => fn () => ['fields' => [], 'files' => ['value' => $this->saturnSonification()]],
                    ],
                    [
                        'type' => 'oembed', 'title' => 'Fly to Mars', 'duration' => '5 min',
                        'introduction' => 'A 360° panorama of Mars from images taken by NASA\'s Perseverance rover. Drag to look around!',
                        'summary' => 'Look for the rover\'s wheels, rocks and the edge of Jezero Crater on the horizon.',
                        'fields' => ['value' => 'https://www.youtube.com/watch?v=Z8RDY0SCf-Y'],
                    ],
                ],
            ],
            [
                'title' => 'Mission 4 · Stars',
                'summary' => 'How stars are born, live and end.',
                'duration' => '12 min',
                'topics' => [
                    [
                        'type' => 'video', 'title' => 'How stars are born', 'duration' => '4 min',
                        'introduction' => 'An animated explainer: from a cloud of gas to a shining star.',
                        'summary' => 'Nebula → gravity squeezes a clump → the centre gets hot enough → the star switches on → leftovers make planets.',
                        'make' => fn () => ['fields' => ['json' => ['chapters' => [
                            ['time' => 5, 'title' => 'A giant cloud'],
                            ['time' => 10, 'title' => 'Gravity pulls it together'],
                            ['time' => 15, 'title' => 'The star switches on'],
                            ['time' => 20, 'title' => 'Leftovers make planets'],
                        ]]], 'files' => [
                            'value' => $this->starsAreBorn(),
                            'poster' => $this->assets->image('nebula-0.png', fn () => NightSkyArt::filmFrame('Mission 4 · Stars', 'How stars are born', '', 'nebula')),
                        ]],
                    ],
                    [
                        'type' => 'h5p', 'title' => 'Star life cycle', 'duration' => '8 min',
                        'introduction' => 'Flip the cards: nebula → main sequence → red giant → white dwarf.',
                        'make' => fn () => $this->starCards(),
                    ],
                ],
            ],
            [
                'title' => 'Mission 5 · Constellations',
                'summary' => 'Draw constellations, then find them in the real sky.',
                'duration' => '15 min',
                'topics' => [
                    [
                        'type' => 'scorm', 'title' => 'Connect the stars', 'duration' => '8 min',
                        'introduction' => 'A game: draw the Big Dipper, Cassiopeia and Orion by connecting the stars.',
                        'summary' => 'Draw all three to earn 120 stars. Hints cost 5 points.',
                        'make' => fn () => $this->scorm('connect-the-stars.zip', 'constellations'),
                    ],
                    [
                        'type' => 'cmi5', 'title' => 'Sky safari', 'duration' => '15 min',
                        'introduction' => 'A backyard observation challenge. Go outside with a grown-up and check in what you saw.',
                        'summary' => 'Spot four things to unlock the Star Finder badge.',
                        'make' => fn () => $this->cmi5('sky-safari.zip', 'sky-safari'),
                    ],
                ],
            ],
            [
                'title' => 'Mission 6 · Galaxies',
                'summary' => 'How big space is, and a quiz to check your explorer skills.',
                'duration' => '15 min',
                'topics' => [
                    [
                        'type' => 'richtext', 'title' => 'How big is space?', 'duration' => '7 min',
                        'introduction' => 'Scale comparisons and simple space maths: light travels 300,000 km every second.',
                        'summary' => 'Moonlight takes 1.3 seconds to reach us, sunlight 8 minutes, starlight from Andromeda 2.5 million years.',
                        'make' => fn () => ['fields' => ['value' => $this->markdown('how-big-is-space', [
                            'space-scale.png' => $this->assets->image('space-scale.png', fn () => NightSkyArt::scale()),
                        ])], 'files' => []],
                    ],
                    [
                        'type' => 'gift', 'title' => 'Mission quiz', 'duration' => '8 min',
                        'introduction' => 'Eight questions, one of each kind. You have 3 hearts (attempts). Good luck, explorer!',
                        'fields' => [
                            'value' => 'Show what you learned on your journey through space!',
                            'max_attempts' => 3,
                            'max_execution_time' => 15,
                            'min_pass_score' => 4,
                            'counts_to_grade' => false,
                            'weight' => 0,
                            'randomize_order' => false,
                        ],
                        'questions' => $this->missionQuiz(),
                    ],
                ],
            ],
            [
                'title' => 'Mission 7 · Your star map',
                'summary' => 'Make your own star map and get a sticker from Dr. Ada.',
                'duration' => '20 min',
                'topics' => [
                    [
                        'type' => 'project', 'title' => 'Build your star map', 'duration' => '20 min',
                        'introduction' => 'Draw or photograph your own constellation map and upload it. Dr. Ada sends you a sticker.',
                        'make' => fn () => ['fields' => [
                            'value' => $this->markdown('project-brief'),
                            'notify_users' => [$this->user('ada')->getKey()],
                            'counts_to_grade' => false,
                            'weight' => 0,
                        ], 'files' => []],
                        'resources' => [fn () => [$this->assets->pdf('star-map-worksheet.pdf', NightSkyArt::starMapWorksheetHtml()), 'star-map-worksheet.pdf']],
                    ],
                    [
                        'type' => 'richtext', 'title' => 'Bonus: space jokes', 'can_skip' => true, 'duration' => '3 min',
                        'introduction' => 'Just for fun!',
                        'make' => fn () => ['fields' => ['value' => $this->markdown('space-jokes')], 'files' => []],
                    ],
                ],
            ],
        ];
    }

    private function moonDiary(): string
    {
        return $this->assets->pdf('moon-diary.pdf', NightSkyArt::moonDiaryHtml(), 'landscape');
    }

    /** @return array<string, mixed> */
    private function moonPhases(): array
    {
        $board = $this->assets->image('moon-orbit.png', fn () => NightSkyArt::moonOrbit());
        $phases = ['New Moon', 'First quarter', 'Full Moon', 'Last quarter'];
        $tray = [2, 0, 3, 1]; // shuffled order in the tray
        $elements = [];
        foreach ($phases as $i => $label) {
            $elements[] = [
                'type' => H5PB::text('<p>' . $label . '</p>', 'H5P.AdvancedText 1.1', $label),
                'x' => 78.5,
                'y' => 16 + array_search($i, $tray, true) * 18,
                'width' => 9.5,
                'height' => 2,
                'dropZones' => ['0', '1', '2', '3'],
                'backgroundOpacity' => 100,
                'multiple' => false,
            ];
        }
        $tips = [
            'The Moon is between the Sun and Earth: the lit side faces away from us.',
            'A quarter of the way round: we see half of the lit side, on the right.',
            'Earth is between the Sun and the Moon: we see the whole lit side.',
            'Three quarters of the way round: half lit again, now on the left.',
        ];
        $zones = [];
        foreach (NightSkyArt::moonZones() as $z => [$x, $y]) {
            $zones[] = [
                'x' => $x - 6.5,
                'y' => $y - 4,
                'width' => 6.5,
                'height' => 2.5,
                'correctElements' => [(string) $z],
                'showLabel' => false,
                'backgroundOpacity' => 0,
                'label' => '<div>' . $phases[$z] . ' position</div>',
                'single' => true,
                'autoAlign' => true,
                'tipsAndFeedback' => ['tip' => $tips[$z], 'feedbackOnCorrect' => 'Yes! ' . $tips[$z], 'feedbackOnIncorrect' => 'Look where the Sun is shining from.'],
            ];
        }

        return $this->h5p('drag-and-drop', 'Why does the Moon change?', [
            'question' => [
                'settings' => ['size' => ['width' => 800, 'height' => 500], 'background' => H5PB::imageInfo($board, 'images/moon-orbit.png')],
                'task' => ['elements' => $elements, 'dropZones' => $zones],
            ],
            'overallFeedback' => [['from' => 0, 'to' => 99, 'feedback' => 'You placed @score of @total. Tip: the half facing the Sun is always lit!'], ['from' => 100, 'to' => 100, 'feedback' => 'Perfect! You earned the Moon Watcher badge.']],
        ], ['images/moon-orbit.png' => $board]);
    }

    /** @return array<string, mixed> */
    private function starCards(): array
    {
        $cards = [
            ['1 · Nebula', 'A giant cloud of gas and dust. Gravity squeezes clumps of it until they get hot enough to glow. Star nurseries!'],
            ['2 · Main sequence star', 'A grown-up star that fuses hydrogen into helium. Our Sun has been doing this for 4.6 billion years.'],
            ['3 · Red giant', 'When the hydrogen runs low, the star swells up and turns red. In 5 billion years our Sun will become one.'],
            ['4 · White dwarf', 'The leftover hot core, about the size of Earth. It slowly cools down over billions of years.'],
            ['Bonus · Supernova', 'Stars much bigger than the Sun end with a giant explosion and can leave a neutron star or a black hole.'],
        ];

        return $this->h5p('dialog-cards', 'Star life cycle', [
            'title' => '<p>The life of a star</p>',
            'description' => '<p>Read the front, guess what happens, then flip the card!</p>',
            'dialogs' => array_map(fn ($c) => [
                'text' => '<p style="text-align:center">' . $c[0] . '</p>',
                'answer' => '<p style="text-align:center">' . $c[1] . '</p>',
                'tips' => (object) [],
            ], $cards),
            'mode' => 'normal',
        ]);
    }

    /** @return array<int, array{type: string, gift: string, score?: int}> */
    private function missionQuiz(): array
    {
        return [
            ['type' => Q::MULTIPLE_CHOICE, 'gift' => '::Biggest planet:: Which planet is the biggest? {=Jupiter ~Mars ~Venus ~Earth}'],
            ['type' => Q::MULTIPLE_CHOICE_WITH_MULTIPLE_RIGHT_ANSWERS, 'gift' => '::Planets:: Which of these are planets? {~%33.33333%Mars ~%33.33333%Neptune ~%33.33334%Venus ~%-100%the Moon}'],
            ['type' => Q::TRUE_FALSE, 'gift' => '::The Sun:: The Sun is a star. {T}'],
            ['type' => Q::SHORT_ANSWERS, 'gift' => '::Big Dipper:: The group of stars that looks like a big spoon (or a plough) is called the Big… {=Dipper =dipper =Plough =plough =Plow}'],
            ['type' => Q::MATCHING, 'gift' => '::Planet facts:: Match each planet with its fact. {=Mercury -> closest to the Sun =Saturn -> famous rings =Mars -> red planet =Neptune -> farthest planet}'],
            ['type' => Q::NUMERICAL_QUESTION, 'gift' => '::How many planets:: How many planets are in our Solar System? {#8}'],
            ['type' => Q::ESSAY, 'gift' => '::Dream trip:: If you could visit one planet, which one and why? {}', 'score' => 2],
            ['type' => Q::DESCRIPTION, 'gift' => '::Stretch break:: Great job, explorer! Stretch your arms before the last questions.', 'score' => 0],
        ];
    }

    // ---------------------------------------------------------- commerce

    protected function commerce(): void
    {
        $family = $this->product('Night Sky Explorers — family subscription', [
            'type' => ProductType::SUBSCRIPTION,
            'price' => 600,
            'description' => 'All seven missions for the whole family, progress emails for parents. Cancel any time.',
            'productables' => [$this->courseProductable()],
            'subscription_period' => PeriodEnum::MONTHLY,
            'subscription_duration' => 1,
            'recursive' => true,
            'has_trial' => true,
            'trial_period' => PeriodEnum::DAILY,
            'trial_duration' => 7,
            'limit_per_user' => 1,
            'tags' => ['kids', 'family'],
        ]);

        $this->product('Night Sky Explorers — classroom package', [
            'type' => ProductType::BUNDLE,
            'price' => 14900,
            'description' => 'Up to 30 pupil seats for one school year, printable worksheets and a teacher\'s guide.',
            'productables' => [$this->courseProductable(30)],
            'limit_per_user' => 5,
        ]);

        $this->webinar('Ask an astronomer — live', [
            'description' => '<p>Kids ask, Dr. Ada answers! Bring your questions about black holes, aliens, the Moon or anything else. Parents welcome.</p>',
            'short_desc' => 'Live Q&A for kids with astrophysicist Dr. Ada Kowalczyk.',
            'agenda' => '<ol><li>What I saw through the telescope this week</li><li>Your questions</li><li>Sky challenge for the weekend</li></ol>',
            'active_from' => '2026-11-21 16:00:00',
            'active_to' => '2026-11-21 17:00:00',
            'duration' => '1',
            'trainers' => [$this->user('ada')->getKey()],
            'tags' => ['astronomy', 'kids'],
            'image' => $this->assets->image('webinar-astronomer.png', fn () => NightSkyArt::filmFrame('Live · 21 Nov · 17:00 CET', 'Ask an astronomer — live', 'Questions from kids, answers from Dr. Ada.', 'planet')),
        ]);

        $this->stationaryEvent('Star party in Kraków', [
            'description' => '<p>Look through real telescopes with Dr. Ada and the Night Sky Explorers team. If the sky is cloudy, we move inside for a planetarium show. Hot chocolate included!</p>',
            'short_desc' => 'An evening of telescopes for kids and parents.',
            'started_at' => '2026-11-22 17:30:00',
            'finished_at' => '2026-11-22 20:30:00',
            'max_participants' => 60,
            'place' => 'Park Jordana, Kraków (meeting point at the main gate)',
            'program' => 'Telescopes, Moon and Saturn viewing, constellation walk',
            'authors' => [$this->user('ada')->getKey()],
            'image' => $this->assets->image('event-star-party.png', fn () => NightSkyArt::filmFrame('Kraków · 22 Nov', 'Star party in Kraków', 'Telescopes, the Moon, Saturn and hot chocolate.', 'orbi')),
        ]);

        $this->coupon('STARPARTY', [
            'name' => 'Star party guests: 50 % off the family subscription',
            'type' => CouponTypeEnum::PRODUCT_PERCENT,
            'amount' => 50,
            'limit_per_user' => 1,
            'active_to' => '2027-01-31 23:59:59',
            'included_products' => [$family->getKey()],
        ]);

        $this->grantAccess($family, 'zosia');
    }
}
