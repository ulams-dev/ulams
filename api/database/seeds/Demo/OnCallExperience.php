<?php

namespace Database\Seeders\Demo;

use App\Models\Consultation;
use Database\Seeders\Demo\Art\OnCallArt;
use Database\Seeders\Demo\Support\H5PPackageBuilder as H5PB;
use Ulams\Cart\Enums\PeriodEnum;
use Ulams\Cart\Enums\ProductType;
use Ulams\TopicTypeGift\Enum\QuestionTypeEnum as Q;
use Ulams\Vouchers\Enums\CouponTypeEnum;

/**
 * Experience 2: "On-Call", professional cohort, dense, dark, keyboard-first.
 */
class OnCallExperience extends DemoExperience
{
    public function key(): string
    {
        return 'oncall';
    }

    protected function palette(): array
    {
        return [
            'background' => OnCallArt::BG,
            'text' => OnCallArt::TEXT,
            'accent' => OnCallArt::BLUE,
            'secondary' => OnCallArt::GREEN,
            'onAccent' => OnCallArt::BG,
            'displayFont' => 'mono',
        ];
    }

    protected function courseFields(): array
    {
        return [
            'title' => 'On-Call — Incident Command for Platform Engineers',
            'subtitle' => 'Run calm incidents, write blameless postmortems, ship reliability',
            'summary' => 'A 4-week cohort with a live game-day. You\'ll command a simulated outage, coordinate responders, communicate to stakeholders and write the postmortem.',
            'description' => $this->markdown('course-description'),
            'level' => 'Advanced',
            'language' => 'en',
            'duration' => '4-week cohort + live drills · 5 modules',
            'hours_to_complete' => 20,
            'target_group' => 'SREs, platform/backend engineers joining on-call, engineering managers',
            'public' => false,
            'fields' => [
                'experience' => 'oncall',
                'cohort' => ['starts_at' => '2026-11-04', 'seats' => 24, 'seats_taken' => 18, 'weeks' => 4],
                'landing' => [
                    'headline' => 'Stay calm at 3 a.m.',
                    'subheadline' => 'A 4-week cohort for engineers who carry the pager.',
                    'cta' => 'Apply for the November cohort',
                    'status_strip' => 'Next cohort: 4 Nov · 18/24 seats',
                    'testimonials' => [
                        ['quote' => '[03:12] first page of my rotation. [03:14] roles assigned. [03:41] mitigated. I had never felt that calm.', 'author' => 'Head of Platform, logistics scale-up'],
                        ['quote' => 'Our postmortems went from blame threads to action items with owners.', 'author' => 'Engineering Manager, payments'],
                        ['quote' => 'The game day is the closest thing to a real SEV1 without losing money.', 'author' => 'Staff Engineer, e-commerce'],
                    ],
                ],
            ],
        ];
    }

    protected function people(): array
    {
        return [
            'priya' => ['first_name' => 'Priya', 'last_name' => 'Raman', 'email' => 'priya.raman@demo.ulams.app', 'role' => 'tutor', 'color' => OnCallArt::BLUE],
            'marek' => ['first_name' => 'Marek', 'last_name' => 'Lis', 'email' => 'marek.lis@demo.ulams.app', 'role' => 'tutor', 'color' => OnCallArt::GREEN],
            'sam' => ['first_name' => 'Sam', 'last_name' => 'Okafor', 'email' => 'sam.okafor@demo.ulams.app', 'role' => 'student', 'color' => OnCallArt::AMBER],
        ];
    }

    protected function categories(): array
    {
        return [
            ['parent' => 'Engineering', 'name' => 'Site reliability'],
            ['parent' => 'Engineering', 'name' => 'Incident management'],
        ];
    }

    protected function tags(): array
    {
        return ['SRE', 'incident response', 'on-call', 'postmortems', 'cohort'];
    }

    protected function courseMedia(): array
    {
        return [
            'image' => $this->assets->image('course-image.png', fn () => OnCallArt::cover('$ cohort · 4 weeks · live drills', 'On-Call — Incident Command for Platform Engineers', 'Run calm incidents, write blameless postmortems, ship reliability')),
            'poster' => $this->assets->image('course-poster.png', fn () => OnCallArt::cover('$ next cohort: 4 Nov · 18/24 seats', 'Stay calm at 3 a.m.', 'A 4-week cohort for engineers who carry the pager.', 1280, 720)),
            'teaser' => $this->kickoffVideo(),
        ];
    }

    protected function certificateName(): string
    {
        return 'Incident Commander — Level 1';
    }

    // ------------------------------------------------------------- media

    private function soundtrack(string $name, int $seconds): string
    {
        // low synth pulse, a slow heartbeat under the frames
        return $this->assets->audio($name, $seconds, '0.09*sin(2*PI*55*t)*(0.6+0.4*sin(2*PI*1.1*t))+0.03*sin(2*PI*110*t)+0.02*sin(2*PI*164.8*t)*(0.5+0.5*sin(2*PI*0.2*t))', 'lowpass=f=1800');
    }

    private function kickoffVideo(): string
    {
        $slides = [
            ['cohort --kickoff', 'Welcome to On-Call', ['4 weeks · 5 modules · 1 live game day', [OnCallArt::GREEN, 'Priya Raman, Staff SRE · Marek Lis, incident commander']]],
            ['schedule --cohort nov', 'Your four weeks', ['week 1  Briefing + Signals', 'week 2  Command', [OnCallArt::AMBER, 'week 3  Game day (live drill, Thu 18:00 CET)'], 'week 4  After: postmortem due Sunday']],
            ['drills --grading', 'How drills are graded', ['time to declare        ≤ 3 min', 'first stakeholder update ≤ 10 min after declaring', 'time to mitigate        ≤ 25 min', 'handover note quality   peer + tutor review']],
            ['rules', 'Three rules', [[OnCallArt::GREEN, '1. Mitigate before you debug.'], [OnCallArt::GREEN, '2. Every message: impact, action, next update.'], [OnCallArt::GREEN, '3. No blame. Ever.']]],
            ['next', 'Start with the rules of engagement', ['→ module 0 · topic 2']],
        ];
        $frames = [];
        foreach ($slides as $i => [$kicker, $title, $lines]) {
            $frames[] = $this->assets->image("kickoff-$i.png", fn () => OnCallArt::filmFrame($kicker, $title, $lines));
        }

        return $this->assets->video('course-kickoff.mp4', $frames, 6, $this->soundtrack('kickoff-pulse.mp3', 26));
    }

    private function takingCommandVideo(): string
    {
        $slides = [
            ['roleplay --sev1 --minute 0', 'Taking command: the first five minutes', ['A role-play recorded with the cohort tutors.']],
            ['03:12:04', 'The page', [[OnCallArt::RED, 'PagerDuty  TRIGGERED checkout-api 5xx 18.4 %'], 'Priya acknowledges in 40 seconds.']],
            ['03:12:50', '"I am the incident commander."', ['Priya: Declaring SEV1. Channel #inc-0412, bridge is open.', 'Priya: Ben, you have comms. Aiko, ops lead. Chen, scribe.', [OnCallArt::GREEN, 'Ben: Comms, ack. Aiko: Ops, ack. Chen: Scribe, ack.']]],
            ['03:14:10', 'Three questions', ['Impact? ~18 % of checkouts failing.', 'Since when? 03:07.', [OnCallArt::AMBER, 'What changed? checkout-api v2.31 at 03:05.']]],
            ['03:16:30', 'Set the clock', ['Priya: Aiko, prepare a rollback to v2.30. Ben, first update now, next at 03:45.', 'Priya: We regroup at 03:25.']],
            ['debrief', 'What to copy', [[OnCallArt::GREEN, 'names + roles in every request'], [OnCallArt::GREEN, 'questions before theories'], [OnCallArt::GREEN, 'a time for the next checkpoint']]],
        ];
        $frames = [];
        foreach ($slides as $i => [$kicker, $title, $lines]) {
            $frames[] = $this->assets->image("command-$i.png", fn () => OnCallArt::filmFrame($kicker, $title, $lines, $i === 1 ? OnCallArt::RED : OnCallArt::BLUE));
        }

        return $this->assets->video('taking-command.mp4', $frames, 6, $this->soundtrack('command-pulse.mp3', 32));
    }

    private function bridgeCall(): string
    {
        // telephone-band murmur of voices, a pager tone and line noise
        $voice = '(sin(2*PI*(120+20*sin(2*PI*0.7*t))*t)+0.6*sin(2*PI*240*t)+0.45*sin(2*PI*510*t)+0.3*sin(2*PI*1150*t))*max(0,sin(2*PI*4.2*t))*between(mod(t,6.5),0.3,4.6)*0.1';
        $voice2 = '(sin(2*PI*(175+25*sin(2*PI*0.5*t))*t)+0.5*sin(2*PI*350*t)+0.4*sin(2*PI*700*t))*max(0,sin(2*PI*3.6*t))*between(mod(t,6.5),4.8,6.4)*0.09';
        $pager = '0.18*sin(2*PI*1046*t)*between(mod(t,30),0,0.12)+0.18*sin(2*PI*1318*t)*between(mod(t,30),0.16,0.28)';
        $line = '0.012*(random(0)*2-1)';

        return $this->assets->audio('bridge-call.mp3', 60, "$voice+$voice2+$pager+$line", 'highpass=f=300,lowpass=f=3400');
    }

    // ----------------------------------------------------------- program

    protected function program(): array
    {
        return [
            [
                'title' => '0. Briefing',
                'summary' => 'Cohort welcome, schedule, grading and the rules everyone follows on the bridge.',
                'duration' => '45 min',
                'topics' => [
                    [
                        'type' => 'video', 'title' => 'Course kickoff', 'preview' => true, 'duration' => '6 min',
                        'introduction' => 'Cohort welcome, the four-week schedule and how drills are graded.',
                        'summary' => 'Week 3 is the live game day. Deadlines: comms quiz Thursday of week 3, postmortem end of week 4.',
                        'description' => "**Transcript**\n\nPRIYA: Welcome to On-Call. Over four weeks you will learn to command an incident, not just to survive one.\n\nMAREK: Week three is the game day. We break a sandbox cluster, you run the incident. We grade time to declare, the first stakeholder update, time to mitigate and your handover note.\n\nPRIYA: Three rules for the whole cohort: mitigate before you debug; every message says impact, action and next update; and no blame. Ever.",
                        'make' => fn () => ['fields' => [], 'files' => [
                            'value' => $this->kickoffVideo(),
                            'poster' => $this->assets->image('kickoff-0.png', fn () => OnCallArt::filmFrame('cohort --kickoff', 'Welcome to On-Call')),
                        ]],
                    ],
                    [
                        'type' => 'richtext', 'title' => 'Rules of engagement', 'duration' => '25 min',
                        'introduction' => 'The severity matrix (SEV1–SEV4) and the four incident roles.',
                        'summary' => 'Declare high, downgrade fast. IC decides, comms talks, ops fixes, scribe writes it down.',
                        'make' => fn () => ['fields' => ['value' => $this->markdown('rules-of-engagement')], 'files' => []],
                    ],
                ],
            ],
            [
                'title' => '1. Signals',
                'summary' => 'Read dashboards like an IC and alert on what users feel.',
                'duration' => '1 h 30 min',
                'topics' => [
                    [
                        'type' => 'image', 'title' => 'Anatomy of an alert', 'duration' => '15 min',
                        'introduction' => 'An annotated dashboard from the moment checkout started failing.',
                        'summary' => 'Start with the symptom (B), rule out traffic (E), follow saturation (C) to the likely cause. Tail latency (D) moves before the median.',
                        'make' => fn () => ['fields' => [], 'files' => ['value' => $this->assets->image('dashboard.png', fn () => OnCallArt::dashboard())]],
                    ],
                    [
                        'type' => 'richtext', 'title' => 'SLOs and error budgets', 'duration' => '40 min',
                        'introduction' => 'SLIs, SLOs, error budgets and burn-rate alerts, with worked examples.',
                        'summary' => 'budget = (1 − SLO) × window: 99.9 % over 30 days is 43.2 minutes. Page on fast burn, ticket on slow burn.',
                        'make' => fn () => ['fields' => ['value' => $this->markdown('slos-and-error-budgets')], 'files' => []],
                    ],
                    [
                        'type' => 'oembed', 'title' => 'Talk: "Alert fatigue is a bug"', 'duration' => '30 min',
                        'introduction' => 'SREcon16 talk "Less Alarming Alerts!" on cutting alert noise. Watch it with your own alert list open.',
                        'summary' => 'Every page should be urgent, actionable and about user impact. Everything else is a ticket or a dashboard.',
                        'fields' => ['value' => 'https://www.youtube.com/watch?v=CdwfNBVkRps'],
                    ],
                ],
            ],
            [
                'title' => '2. Command',
                'summary' => 'Take command, keep the bridge disciplined and decide under pressure.',
                'duration' => '2 h',
                'topics' => [
                    [
                        'type' => 'video', 'title' => 'Taking command', 'duration' => '12 min',
                        'introduction' => 'Role-play of the first five minutes of a SEV1.',
                        'summary' => 'Names and roles in every request, questions before theories, a time for the next checkpoint.',
                        'make' => fn () => ['fields' => ['json' => ['chapters' => [
                            ['time' => 5, 'title' => 'The page'],
                            ['time' => 10, 'title' => 'Declaring and assigning roles'],
                            ['time' => 15, 'title' => 'Three questions'],
                            ['time' => 20, 'title' => 'Setting the clock'],
                            ['time' => 25, 'title' => 'Debrief'],
                        ]]], 'files' => [
                            'value' => $this->takingCommandVideo(),
                            'poster' => $this->assets->image('command-1.png', fn () => OnCallArt::filmFrame('03:12:04', 'The page', [], OnCallArt::RED)),
                        ]],
                    ],
                    [
                        'type' => 'audio', 'title' => 'Radio discipline', 'duration' => '10 min',
                        'introduction' => 'A recorded incident bridge call. Listen, read the transcript and spot the five mistakes.',
                        'summary' => 'Mistakes: no IC declared, "can someone…" requests, two conversations at once, a guess in the public channel, no next-update time.',
                        'description' => "**Transcript (excerpt)**\n\n`03:13` **Dev 1:** Is anyone looking at checkout? *(mistake 1: nobody takes command)*\n\n`03:13` **Dev 2:** Can someone check the database? *(mistake 2: request with no name)*\n\n`03:14` **Dev 3:** I'm restarting the pods… **Dev 1:** wait, I'm also looking at the load balancer *(mistake 3: two conversations, conflicting actions)*\n\n`03:16` **Dev 2 in #general:** Probably Ben's deploy again *(mistake 4: guess and blame in public)*\n\n`03:18` **Manager:** When will it be fixed? **Dev 1:** Soon. *(mistake 5: no impact, no next update time)*\n\n**Rewrite one minute of this call** with roles, names and a next-update time, and bring it to the live session.\n\n*The recording is a soundscape of a bridge line; the dialogue is in this transcript.*",
                        'make' => fn () => ['fields' => [], 'files' => ['value' => $this->bridgeCall()]],
                    ],
                    [
                        'type' => 'h5p', 'title' => 'Who does what?', 'duration' => '20 min',
                        'introduction' => 'A branching scenario: you are the IC of a SEV1. Every choice changes what happens next.',
                        'make' => fn () => $this->branchingScenario(),
                    ],
                    [
                        'type' => 'pdf', 'title' => 'Incident runbook template', 'duration' => '15 min',
                        'introduction' => 'A printable runbook: severity matrix, roles, the first five minutes, comms templates and a contacts page.',
                        'summary' => 'Print it, fill in the contacts page for your team and keep it next to your laptop.',
                        'make' => fn () => ['fields' => [], 'files' => ['value' => $this->runbook()]],
                        'resources' => [fn () => [$this->runbook(), 'incident-runbook.pdf']],
                    ],
                ],
            ],
            [
                'title' => '3. Game day',
                'summary' => 'Command a simulated cascading failure, run the live drill and check your comms.',
                'duration' => '3 h',
                'topics' => [
                    [
                        'type' => 'scorm', 'title' => 'Outage simulator', 'duration' => '45 min',
                        'introduction' => 'A simulated cascading failure with live metrics, a timeline and a chat pane. Keyboard: 1–9.',
                        'summary' => 'Score 70+ to pass. Your timeline from the simulator is the input for the postmortem in module 4.',
                        'make' => fn () => $this->scorm('outage-simulator.zip', 'outage-simulator'),
                    ],
                    [
                        'type' => 'liascript', 'title' => 'Severity levels', 'duration' => '10 min',
                        'introduction' => 'An interactive LiaScript lesson on choosing the incident severity, with a quick check.',
                        'summary' => 'SEV1 to SEV3: who gets paged, how fast you update, when you write a postmortem.',
                        'make' => fn () => $this->liascript('Severity levels', <<<'MD'
# Severity levels

The severity decides who gets paged and how often the status page is updated. Pick it fast, change
it when you learn more.

## SEV1

Customers cannot use the product, or data is at risk. Page the incident commander and the on-call
engineers of every affected service. Status update every 30 minutes. Postmortem required.

## SEV2 and SEV3

- **SEV2**: a major feature is degraded for many customers. Page the owning team; update hourly.
- **SEV3**: a minor feature is broken or a workaround exists. Handle in working hours.

## Check yourself

Checkout fails for 40 % of customers. Which severity?

- [(X)] SEV1
- [( )] SEV2
- [( )] SEV3

How often do you update the status page during a SEV1? (minutes)

[[30]]
MD),
                    ],
                    [
                        'type' => 'cmi5', 'title' => 'Live drill', 'duration' => '90 min',
                        'introduction' => 'The xAPI-tracked live drill in the sandbox cluster drill-eu-1. Thursday of week 3, 18:00 CET.',
                        'summary' => 'Tick each checkpoint as it happens; the timestamps go to your learning record and to your facilitator.',
                        'make' => fn () => $this->cmi5('live-drill.zip', 'live-drill'),
                    ],
                    [
                        'type' => 'gift', 'title' => 'Comms check', 'duration' => '20 min',
                        'introduction' => 'Eight questions on incident communication. Two attempts, 15 minutes. Due Thursday of week 3.',
                        'fields' => [
                            'value' => 'Incident communication and severity. Answer in English; the essay is graded by a tutor (max 80 words).',
                            'max_attempts' => 2,
                            'max_execution_time' => 15,
                            'min_pass_score' => 6,
                            'counts_to_grade' => true,
                            'weight' => 30,
                            'randomize_order' => false,
                        ],
                        'questions' => $this->commsCheck(),
                    ],
                ],
            ],
            [
                'title' => '4. After',
                'summary' => 'Turn the outage into learning: blameless postmortem, review, reading.',
                'duration' => '3 h',
                'topics' => [
                    [
                        'type' => 'richtext', 'title' => 'Blameless postmortems', 'duration' => '35 min',
                        'introduction' => 'The template, the five whys used carefully, and contributing factors vs root cause.',
                        'summary' => 'Describe conditions, not culprits. Several contributing factors, several owned and dated action items.',
                        'make' => fn () => ['fields' => ['value' => $this->markdown('blameless-postmortems', [
                            'postmortem-timeline.png' => $this->assets->image('postmortem-timeline.png', fn () => OnCallArt::postmortemTimeline()),
                        ])], 'files' => []],
                    ],
                    [
                        'type' => 'project', 'title' => 'Postmortem assignment', 'duration' => '2 h',
                        'introduction' => 'Write the postmortem for the game-day outage in Markdown or PDF. Peer and tutor review.',
                        'make' => fn () => ['fields' => [
                            'value' => $this->markdown('project-brief'),
                            'notify_users' => $this->tutorIds(),
                            'counts_to_grade' => true,
                            'weight' => 70,
                            'max_score' => 15,
                        ], 'files' => []],
                        'resources' => [fn () => [$this->assets->pdf('postmortem-template.pdf', OnCallArt::postmortemTemplateHtml()), 'postmortem-template.pdf']],
                    ],
                    [
                        'type' => 'richtext', 'title' => 'Reading list', 'can_skip' => true, 'duration' => '10 min',
                        'introduction' => 'Papers and books the cohort keeps coming back to.',
                        'make' => fn () => ['fields' => ['value' => $this->markdown('reading-list')], 'files' => []],
                    ],
                ],
            ],
        ];
    }

    private function runbook(): string
    {
        return $this->assets->pdf('incident-runbook.pdf', OnCallArt::runbookHtml());
    }

    /** @return array<string, mixed> */
    private function branchingScenario(): array
    {
        $text = fn (string $html, string $title) => H5PB::text($html, 'H5P.AdvancedText 1.1', $title);
        $question = fn (string $q, array $alternatives, string $title) => [
            'library' => 'H5P.BranchingQuestion 1.0',
            'params' => ['branchingQuestion' => [
                'question' => '<p>' . $q . '</p>',
                'alternatives' => array_map(fn ($a) => [
                    'text' => $a[0],
                    'nextContentId' => $a[1],
                    'feedback' => ['title' => $a[2] ?? '', 'subtitle' => $a[3] ?? '', 'endScreenScore' => $a[4] ?? 0],
                ], $alternatives),
            ]],
            'subContentId' => H5PB::uuid(),
            'metadata' => ['contentType' => 'Branching Question', 'license' => 'U', 'title' => $title],
        ];
        $node = fn (array $type, ?int $next = null) => array_filter([
            'type' => $type,
            'showContentTitle' => false,
            'feedback' => ['subtitle' => ''],
            'nextContentId' => $next,
            'contentBehaviour' => 'useBehavioural',
            'forceContentFinished' => 'useBehavioural',
            'proceedButtonText' => 'Continue',
        ], fn ($v) => $v !== null);

        $content = [
            // 0
            $node($text('<h2>03:12 — the page</h2><p>PagerDuty: <strong>checkout-api 5xx at 18.4 %</strong>. You are the primary on-call. Aiko (ops) is already online. Nobody has said who is in charge.</p>', 'The page'), 1),
            // 1
            $node($question('What do you do first?', [
                ['Declare SEV1, open #inc-0412 and assign comms, ops lead and scribe', 2],
                ['Open the logs and start debugging yourself', 5],
                ['Wait ten minutes to see whether it recovers on its own', -1, 'Customers waited too', 'At 03:22 support had 140 tickets and the VP of Engineering paged you. Nobody was in charge for ten minutes. Always declare first: downgrading is cheap.', 0],
            ], 'First move')),
            // 2
            $node($text('<h2>03:14 — roles assigned</h2><p>Ben has comms, Aiko is ops lead, Chen is scribe. Aiko reports: <em>"checkout-api v2.31 went out at 03:05. The DB pool is at 100/100."</em></p>', 'Roles assigned'), 3),
            // 3
            $node($question('Aiko asks: roll back v2.31 now, or keep debugging the new code path?', [
                ['Roll back now. Mitigate first, find the bug later', 4],
                ['Keep debugging, we are close to the fix', 6],
                ['Double the database max_connections', 7],
            ], 'Mitigation')),
            // 4
            $node($question('03:31 — errors drop to 0.4 %. Ben asks what to post. Which update do you approve?', [
                ['"Checkout errors affected ~18 % of customers from 03:07 to 03:30. We reverted a change; service is recovering. Next update 03:45."', -1, 'Calm, fast, blameless', 'You declared in 2 minutes, mitigated in 19 and told stakeholders exactly what they needed. This is the standard for the drill.', 10],
                ['"Fixed. Root cause was Ben\'s deploy."', -1, 'Mitigated, but…', 'The outage is over, but the update blames a person and gives no impact or next steps. Expect a defensive postmortem. Re-read the blameless module.', 5],
                ['Post nothing until the postmortem is written', -1, 'Silence is a message', 'Customers and executives filled the silence with guesses. An update needs only impact, action and next update time.', 4],
            ], 'Stakeholder update')),
            // 5
            $node($text('<h2>03:32 — twenty minutes in the logs</h2><p>You found an interesting stack trace. Meanwhile three people restarted the same pods, support has 140 tickets and the VP of Engineering asks in #general who is in charge.</p><p><strong>Lesson:</strong> the IC does not debug. Go back and take command.</p>', 'Lost in the logs'), 1),
            // 6
            $node($text('<h2>03:48 — still debugging</h2><p>The fix is "almost ready" for the third time. The error budget for the month is half gone and retries now overload payment-gateway too. Aiko suggests the rollback again.</p>', 'Still debugging'), 3),
            // 7
            $node($text('<h2>03:20 — more connections, more pain</h2><p>The database CPU hits 100 %. Every new connection runs the same slow query, and payments start timing out. You have turned one failing service into two.</p>', 'Cascading failure'), 3),
        ];

        return $this->h5p('branching-scenario', 'Who does what? An IC scenario', [
            'branchingScenario' => [
                'content' => $content,
                'startScreen' => [
                    'startScreenTitle' => '<p>Who does what?</p>',
                    'startScreenSubtitle' => '<p>You are the incident commander of a SEV1. Every choice changes the outcome.</p>',
                ],
                'endScreens' => [[
                    'endScreenTitle' => '<p>Incident closed</p>',
                    'endScreenSubtitle' => '<p>Compare your path with the runbook: declare, assign, mitigate, communicate.</p>',
                    'contentId' => -1,
                    'endScreenScore' => 0,
                ]],
                'behaviour' => ['enableBackwardsNavigation' => false, 'forceContentFinished' => false, 'randomizeBranchingQuestions' => false],
                'scoringOptionGroup' => ['scoringOption' => 'static-end-score', 'includeInteractionsScores' => false],
                'l10n' => [
                    'startScreenButtonText' => 'Take the page',
                    'endScreenButtonText' => 'Run it again',
                    'scoreText' => 'Your score:',
                    'backButtonText' => 'Back',
                    'disableProceedButtonText' => 'Finish this step first',
                    'replayButtonText' => 'Replay the video',
                    'fullscreenAria' => 'Fullscreen',
                ],
            ],
        ]);
    }

    /** @return array<int, array{type: string, gift: string, score?: int}> */
    private function commsCheck(): array
    {
        return [
            ['type' => Q::MULTIPLE_CHOICE, 'gift' => '::First IC action:: What is the first thing the IC does when paged into a SEV1? {=Declare roles and open the incident channel ~Start debugging ~Email the CTO ~Roll back everything}'],
            ['type' => Q::MULTIPLE_CHOICE_WITH_MULTIPLE_RIGHT_ANSWERS, 'gift' => '::Status update:: What belongs in a status update? {~%33.33333%Impact ~%33.33333%Current action ~%33.33334%Next update time ~%-100%Suspected engineer\'s name}'],
            ['type' => Q::TRUE_FALSE, 'gift' => '::Naming people:: A postmortem should name the person who caused the outage. {F}'],
            ['type' => Q::SHORT_ANSWERS, 'gift' => '::TTM:: The time from detection to mitigation is called… {=time to mitigate =Time to mitigate =TTM =ttm}'],
            ['type' => Q::MATCHING, 'gift' => '::Severity levels:: Match each severity level with its definition. {=SEV1 -> customer-facing outage =SEV2 -> major degradation =SEV3 -> minor, workaround exists =SEV4 -> cosmetic}'],
            ['type' => Q::NUMERICAL_QUESTION, 'gift' => '::Error budget:: How many minutes of error budget does a 99.95 % SLO allow over 30 days? {#21.6:0.1}'],
            ['type' => Q::ESSAY, 'gift' => '::First update:: Write the first stakeholder update for the game-day incident (max 80 words). {}', 'score' => 2],
            ['type' => Q::DESCRIPTION, 'gift' => '::Heads-up:: Drill starts in 2 minutes — open the sandbox.', 'score' => 0],
        ];
    }

    // ---------------------------------------------------------- commerce

    protected function commerce(): void
    {
        $seat = $this->product('On-Call — Incident Command (1 seat)', [
            'type' => ProductType::SINGLE,
            'price' => 49000,
            'description' => 'One seat in the November cohort: 4 weeks, live game day, reviewed postmortem, certificate.',
            'productables' => [$this->courseProductable()],
            'limit_per_user' => 1,
            'limit_total' => 24,
            'tags' => ['sre', 'cohort'],
        ]);

        $team = $this->product('On-Call for Teams (annual)', [
            'type' => ProductType::SUBSCRIPTION,
            'price' => 390000,
            'description' => 'Up to 10 seats per year across cohorts, a private game day for your team and a quarterly on-call review.',
            'productables' => [$this->courseProductable(10)],
            'subscription_period' => PeriodEnum::YEARLY,
            'subscription_duration' => 1,
            'recursive' => false,
            'has_trial' => false,
            'limit_per_user' => 1,
        ]);

        $consultation = $this->consultation('On-call review 1:1', [
            'description' => '<p>A 45-minute video call with Priya or Marek. Bring your alert list, your severity matrix or your last postmortem; leave with three concrete changes for your rotation.</p>',
            'short_desc' => 'Book a 1:1 on-call review with a tutor.',
            'duration' => '45 minutes',
            'author_id' => $this->user('priya')->getKey(),
            'active_from' => '2026-10-12 08:00:00',
            'active_to' => '2027-03-31 18:00:00',
            'max_session_students' => 1,
            'teachers' => [$this->user('priya')->getKey(), $this->user('marek')->getKey()],
            'proposed_terms' => ['2026-11-06 09:00:00', '2026-11-06 15:00:00', '2026-11-13 09:00:00', '2026-11-13 15:00:00', '2026-11-20 12:00:00'],
            'image' => $this->assets->image('consultation.png', fn () => OnCallArt::filmFrame('book --review 1:1', 'Book an on-call review', ['45 min · video call · Priya Raman or Marek Lis'])),
        ]);
        $this->product('On-call review 1:1 (45 min)', [
            'type' => ProductType::SINGLE,
            'price' => 15000,
            'description' => 'One 45-minute review of your alerts, rotation or postmortem.',
            'productables' => [['id' => $consultation->getKey(), 'class' => Consultation::class, 'quantity' => 1]],
            'limit_per_user' => 3,
        ]);

        $this->webinar('Postmortem teardown — live', [
            'description' => '<p>Marek takes a real public postmortem apart live: timeline, contributing factors and action items, and rewrites the parts that slipped into blame.</p>',
            'short_desc' => 'Live teardown of a public postmortem with Marek Lis.',
            'agenda' => '<ol><li>The incident in five minutes</li><li>What the timeline hides</li><li>Rewriting contributing factors</li><li>Action items that actually ship</li><li>Q&amp;A</li></ol>',
            'active_from' => '2026-11-19 17:00:00',
            'active_to' => '2026-11-19 18:00:00',
            'duration' => '1',
            'trainers' => [$this->user('marek')->getKey()],
            'tags' => ['postmortems', 'sre'],
            'image' => $this->assets->image('webinar-teardown.png', fn () => OnCallArt::filmFrame('webinar --live 19 Nov 18:00 CET', 'Postmortem teardown — live', ['with Marek Lis'])),
        ]);

        $this->stationaryEvent('Game day in Warsaw', [
            'description' => '<p>An in-person game day for teams: two simulated outages, a real bridge, real pressure, and a facilitated postmortem over lunch.</p>',
            'short_desc' => 'Full-day in-person incident drill for teams.',
            'started_at' => '2026-11-27 09:00:00',
            'finished_at' => '2026-11-27 17:00:00',
            'max_participants' => 24,
            'place' => 'Warsaw, Koszyki Hall, ul. Koszykowa 63',
            'program' => 'Two drills, postmortem workshop, Q&A',
            'authors' => [$this->user('marek')->getKey(), $this->user('priya')->getKey()],
            'image' => $this->assets->image('event-gameday.png', fn () => OnCallArt::filmFrame('event --warsaw 27 Nov', 'Game day in Warsaw', ['two outages · one bridge · lunch postmortem'])),
        ]);

        $this->coupon('ACME-ONCALL-20', [
            'name' => 'ACME Corp: company voucher, 20 % off seats',
            'type' => CouponTypeEnum::PRODUCT_PERCENT,
            'amount' => 20,
            'limit_usage' => 10,
            'limit_per_user' => 1,
            'active_to' => '2027-06-30 23:59:59',
            'included_products' => [$seat->getKey()],
        ]);

        $this->grantAccess($seat, 'sam');
    }
}
