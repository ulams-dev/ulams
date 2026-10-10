<?php

namespace Ulams\CourseBuilder\Fake;

use Ulams\Ai\Dto\DriverRequest;
use Ulams\Ai\Fake\FakeResponders;

/**
 * Deterministic stand-ins for the model, used by the fake driver in `synthetic` mode when no
 * cassette matches: local demos without an API key and the end-to-end test. They read the same
 * request the model would get (fragments, brief, task input) and return schema-valid answers built
 * from the source text, so every citation is real. They are not a substitute for evals.
 */
final class SyntheticResponders
{
    public static function register(FakeResponders $responders): void
    {
        $responders->register('interview', fn (DriverRequest $r) => self::interview($r));
        $responders->register('outline', fn (DriverRequest $r) => self::outline($r));
        $responders->register('lesson', fn (DriverRequest $r) => self::lesson($r));
        $responders->register('grounding', fn (DriverRequest $r) => ['unsupported' => []]);
        $responders->register('quiz', fn (DriverRequest $r) => self::quiz($r));
        $responders->register('global', fn (DriverRequest $r) => self::global($r));
        $responders->register('selfcheck', fn (DriverRequest $r) => self::quiz($r));
        $responders->register('interaction_h5p', fn (DriverRequest $r) => self::h5p($r));
        $responders->register('interaction_interactive', fn (DriverRequest $r) => self::interactive($r));
        $responders->register('metadata', fn (DriverRequest $r) => self::metadata($r));
        $responders->register('price', fn (DriverRequest $r) => self::price($r));
        $responders->register('patch', fn (DriverRequest $r) => self::patch($r));
    }

    /** @return array<string,array{id:string,section:string,text:string}> */
    public static function fragments(DriverRequest $r): array
    {
        $out = [];
        foreach ($r->blocks as $block) {
            preg_match_all('/<fragment id="(frg_[a-z2-7]{12})" section="([^"]*)"[^>]*>\n(.*?)\n<\/fragment>/s', $block->text, $m, PREG_SET_ORDER);
            foreach ($m as $match) {
                $out[$match[1]] = ['id' => $match[1], 'section' => html_entity_decode($match[2], ENT_QUOTES | ENT_XML1), 'text' => html_entity_decode($match[3], ENT_QUOTES | ENT_XML1)];
            }
        }

        return $out;
    }

    private static function tag(DriverRequest $r, string $tag): ?string
    {
        foreach ($r->blocks as $block) {
            if (preg_match("/<{$tag}[^>]*>(.*?)<\/{$tag}>/s", $block->text, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    private static function input(DriverRequest $r): array
    {
        return (array) json_decode((string) self::tag($r, 'task_input'), true);
    }

    private static function brief(DriverRequest $r): array
    {
        return (array) json_decode((string) self::tag($r, 'course_brief'), true);
    }

    private static function sourceTitle(DriverRequest $r): string
    {
        foreach ($r->blocks as $block) {
            if (preg_match('/<source title="([^"]*)">/', $block->text, $m)) {
                return html_entity_decode($m[1], ENT_QUOTES | ENT_XML1);
            }
        }

        return 'Your topic';
    }

    /** @return string[] */
    private static function sentences(string $text): array
    {
        $text = (string) preg_replace('/```.*?```/s', '', $text);
        // a well-behaved stand-in: never repeats markup from the (untrusted) source
        $text = strip_tags((string) preg_replace('/<script\b.*?<\/script>/is', '', $text));
        $text = (string) preg_replace('/^\s*([#>*\-|]+|\d+\.)\s*/m', '', $text);
        $text = trim((string) preg_replace('/\s+/', ' ', str_replace(['**', '__', '`'], '', $text)));
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9])/u', $text) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($s) => str_word_count($s) >= 5 && mb_strlen($s) <= 400));
    }

    private static function title(string $section): string
    {
        return trim((string) preg_replace('/^§[\d.]+\s*/', '', $section)) ?: 'Introduction';
    }

    private static function interview(DriverRequest $r): array
    {
        $count = count(self::fragments($r));
        $total = $count < 12 ? 30 : ($count < 30 ? 60 : 120);
        $lang = (string) (self::input($r)['sourceLanguage'] ?? 'en');

        return [
            'message' => sprintf('I read "%s" (%d sections). A few quick questions and I will propose an outline; skip anything and I will use sensible defaults.', self::sourceTitle($r), $count),
            'questions' => [
                ['key' => 'audience', 'label' => 'Who is this course for?', 'why' => 'The source reads like practical guidance, so I would aim it at people applying it at home or at work.', 'component' => 'ChoiceChips',
                    'options' => [['value' => 'Beginners at home', 'label' => 'Beginners at home'], ['value' => 'Professionals', 'label' => 'Professionals'], ['value' => 'Students', 'label' => 'Students']], 'defaultValue' => 'Beginners at home'],
                ['key' => 'level', 'label' => 'What level should it start at?', 'why' => 'The source explains its basics, so starting from zero works.', 'component' => 'SingleChoice',
                    'options' => [['value' => 'beginner', 'label' => 'Beginner'], ['value' => 'intermediate', 'label' => 'Intermediate'], ['value' => 'advanced', 'label' => 'Advanced']], 'defaultValue' => 'beginner'],
                ['key' => 'duration', 'label' => 'How long should the course be?', 'why' => sprintf('The source has %d sections; %d minutes covers all of them.', $count, $total), 'component' => 'DurationSlider',
                    'options' => [['value' => '30', 'label' => '30 min'], ['value' => '60', 'label' => '1 h'], ['value' => '120', 'label' => '2 h']], 'defaultValue' => "{$total}|10"],
                ['key' => 'tone', 'label' => 'Which tone fits your learners?', 'why' => 'A friendly tone suits a practical topic.', 'component' => 'ChoiceChips',
                    'options' => [['value' => 'friendly', 'label' => 'Friendly'], ['value' => 'professional', 'label' => 'Professional'], ['value' => 'playful', 'label' => 'Playful'], ['value' => 'academic', 'label' => 'Academic']], 'defaultValue' => 'friendly'],
                ['key' => 'assessments', 'label' => 'How should learners check their progress?', 'why' => 'Short quizzes after each lesson help learners remember the key numbers.', 'component' => 'ChoiceChips',
                    'options' => [['value' => 'quiz', 'label' => 'Quiz after each lesson'], ['value' => 'final', 'label' => 'Final test']], 'defaultValue' => 'quiz'],
                ['key' => 'language', 'label' => 'Which language should the course use?', 'why' => 'The source is written in this language.', 'component' => 'LanguagePicker',
                    'options' => [['value' => $lang, 'label' => strtoupper($lang)], ['value' => 'en', 'label' => 'English']], 'defaultValue' => $lang],
            ],
        ];
    }

    private static function outline(DriverRequest $r): array
    {
        $fragments = array_values(self::fragments($r));
        $brief = self::brief($r);
        $total = (int) ($brief['totalMinutes'] ?? 60);
        // group by top-level section number
        $groups = [];
        foreach ($fragments as $f) {
            $top = preg_match('/^§(\d+)/', $f['section'], $m) ? $m[1] : '1';
            $groups[$top][] = $f;
        }
        $groups = array_slice($groups, 0, 4, true);
        $lessonSpecs = [];
        foreach ($groups as $top => $list) {
            $bySection = [];
            foreach ($list as $f) {
                $bySection[$f['section']][] = $f;
            }
            if (count($bySection) > 1) {
                $bySection = array_filter($bySection, fn ($fs, $section) => preg_match('/^§\d+\.\d+/', $section) || count($bySection) === 1, ARRAY_FILTER_USE_BOTH) ?: $bySection;
            }
            $lessonSpecs[$top] = array_slice($bySection, 0, 3, true);
        }
        $count = array_sum(array_map('count', $lessonSpecs)) ?: 1;
        $per = max(1, intdiv($total, $count));
        $remaining = $total;
        $modules = [];
        $n = 0;
        foreach ($lessonSpecs as $top => $sections) {
            $first = $groups[$top][0];
            $lessons = [];
            foreach ($sections as $section => $fs) {
                $n++;
                $minutes = $n === $count ? max(1, $remaining) : $per;
                $remaining -= $minutes;
                $title = self::title($section);
                $cites = array_slice(array_column($fs, 'id'), 0, 3);
                $lessons[] = [
                    'title' => $title,
                    'summary' => 'What the source says about ' . mb_strtolower($title) . '.',
                    'minutes' => $minutes,
                    'objectives' => [['text' => 'Explain the main points of ' . mb_strtolower($title), 'citations' => [$cites[0]]]],
                    'citations' => $cites,
                ];
            }
            $heading = null;
            foreach ($groups[$top] as $f) {
                if (preg_match('/^§\d+\s/', $f['section'])) {
                    $heading = self::title($f['section']);
                    break;
                }
            }
            $modules[] = ['title' => $heading ?? self::title($first['section']), 'summary' => 'Lessons drawn from section ' . $top . ' of the source.', 'lessons' => $lessons];
        }

        return [
            'message' => 'I followed the order of your source and kept every lesson to what it covers.',
            'course' => [
                'title' => self::sourceTitle($r),
                'subtitle' => 'A short course built from your own material',
                'description' => 'This course follows the source section by section. Every lesson cites the passage it comes from.',
                'objectives' => array_map(fn ($m) => ['text' => 'Apply what ' . $m['title'] . ' covers', 'citations' => [$m['lessons'][0]['citations'][0]]], $modules),
            ],
            'modules' => $modules,
        ];
    }

    private static function lesson(DriverRequest $r): array
    {
        $input = self::input($r);
        $all = self::fragments($r);
        $lesson = $input['lesson'] ?? [];
        $ids = array_values(array_filter($lesson['fragments'] ?? [], fn ($id) => isset($all[$id]))) ?: [array_key_first($all)];
        $objectiveIds = array_column($lesson['objectives'] ?? [], 'id');
        $blocks = [];
        foreach (array_slice($ids, 0, 3) as $i => $id) {
            $sentences = self::sentences($all[$id]['text']) ?: [mb_substr($all[$id]['text'], 0, 300)];
            $blocks[] = [
                'kind' => 'paragraph',
                'markdown' => implode(' ', array_slice($sentences, 0, 3)),
                'citations' => [$id],
                'objectiveIds' => $i === 0 ? $objectiveIds : [$objectiveIds[0]],
            ];
        }
        $key = self::sentences($all[$ids[0]]['text'])[0] ?? null;
        if ($key !== null) {
            $blocks[] = ['kind' => 'callout', 'markdown' => '**Key takeaway:** ' . $key, 'citations' => [$ids[0]], 'objectiveIds' => [$objectiveIds[0]]];
        }

        return ['blocks' => $blocks];
    }

    private static function quiz(DriverRequest $r): array
    {
        $input = self::input($r);
        $all = self::fragments($r);
        $count = (int) ($input['count'] ?? 3);
        $targets = [];
        if (!empty($input['finalTest'])) {
            foreach ($input['objectives'] ?? [] as $o) {
                $targets[] = ['objective' => $o['id'], 'fragment' => $o['fragments'][0] ?? null];
            }
        } else {
            $objective = $input['lesson']['objectives'][0]['id'] ?? '';
            foreach ($input['blocks'] ?? [] as $b) {
                foreach ($b['citations'] as $id) {
                    $targets[] = ['objective' => $objective, 'fragment' => $id];
                }
            }
        }
        $targets = array_values(array_filter($targets, fn ($t) => isset($all[$t['fragment']])));
        $pool = [];
        foreach ($all as $f) {
            foreach (self::sentences($f['text']) as $s) {
                $pool[] = ['fragment' => $f['id'], 'text' => $s];
            }
        }
        $questions = [];
        $used = [];
        for ($i = 0; $i < $count && $targets !== []; $i++) {
            $t = $targets[$i % count($targets)];
            $candidates = array_values(array_filter(self::sentences($all[$t['fragment']]['text']), fn ($s) => !isset($used[$s])));
            if ($candidates === []) {
                continue;
            }
            $right = mb_substr($candidates[0], 0, 480);
            $used[$candidates[0]] = true;
            $wrong = [];
            foreach ($pool as $p) {
                if ($p['fragment'] !== $t['fragment'] && !in_array($p['text'], $wrong, true) && count($wrong) < 2) {
                    $wrong[] = mb_substr($p['text'], 0, 480);
                }
            }
            while (count($wrong) < 2) {
                $wrong[] = count($wrong) === 0 ? 'The source recommends the opposite of this.' : 'The source does not mention this topic.';
            }
            $options = [['text' => $wrong[0], 'correct' => false], ['text' => $right, 'correct' => true], ['text' => $wrong[1], 'correct' => false]];
            $questions[] = [
                'type' => 'single',
                'stem' => 'Which statement matches what the source says in ' . $all[$t['fragment']]['section'] . '?',
                'options' => $options,
                'explanation' => 'The source states: ' . $right,
                'citations' => [$t['fragment']],
                'objectiveIds' => [$t['objective']],
            ];
        }

        return ['questions' => $questions];
    }

    /** A stand-in for a whole-course edit: the same text with a visible mark, so tests and demos see what changed. */
    private static function global(DriverRequest $r): array
    {
        $input = self::input($r);
        $instruction = (array) json_decode((string) self::tag($r, 'global_instruction'), true);
        $mark = ($instruction['kind'] ?? '') === 'translate' ? '[' . ($instruction['value'] ?? 'xx') . '] ' : '[' . ($instruction['value'] ?: 'edited') . '] ';
        $t = fn (string $text) => $text === '' ? '' : $mark . $text;
        $questions = fn (array $qs) => array_map(fn ($q) => ['id' => $q['id'], 'stem' => $t($q['stem']), 'options' => array_map(fn ($o) => ['id' => $o['id'], 'text' => $t($o['text'])], $q['options']), 'explanation' => $t($q['explanation'])], $qs);
        if (isset($input['blocks'])) {
            return ['title' => $t($input['title']), 'summary' => $t((string) $input['summary']),
                'objectives' => array_map(fn ($o) => ['id' => $o['id'], 'text' => $t($o['text'])], $input['objectives']),
                'blocks' => array_map(fn ($b) => ['id' => $b['id'], 'markdown' => $t($b['markdown'])], $input['blocks']),
                'questions' => $questions($input['questions']), 'selfChecks' => $questions($input['selfChecks'] ?? [])];
        }
        if (isset($input['modules'])) {
            return ['title' => $t($input['title']), 'subtitle' => $t((string) $input['subtitle']), 'description' => $t((string) $input['description']),
                'objectives' => array_map(fn ($o) => ['id' => $o['id'], 'text' => $t($o['text'])], $input['objectives']),
                'modules' => array_map(fn ($m) => ['id' => $m['id'], 'title' => $t($m['title']), 'summary' => $t((string) $m['summary'])], $input['modules']),
                'faq' => array_map(fn ($f) => ['question' => $t($f['question']), 'answer' => $t($f['answer'])], $input['faq'])];
        }

        return ['questions' => $questions($input['questions'])];
    }

    /** Sentences of the lesson's cited fragments, each with its fragment id. @return array<int,array{id:string,text:string}> */
    private static function lessonSentences(DriverRequest $r): array
    {
        $all = self::fragments($r);
        $out = [];
        foreach ((array) (self::input($r)['blocks'] ?? []) as $block) {
            foreach ($block['citations'] ?? [] as $id) {
                foreach (isset($all[$id]) ? self::sentences($all[$id]['text']) : [] as $sentence) {
                    $out[$sentence] = ['id' => $id, 'text' => $sentence];
                }
            }
        }

        return array_values($out);
    }

    /** A word of at least five letters to blank out of a sentence: letters only, so the markers stay valid. */
    private static function pickWord(string $sentence): ?string
    {
        preg_match_all('/\b\p{L}{5,}\b/u', $sentence, $m);

        return $m[0][0] ?? null;
    }

    private static function h5p(DriverRequest $r): array
    {
        $input = self::input($r);
        $library = (string) ($input['libraries'][0] ?? 'H5P.Blanks');
        $sentences = self::lessonSentences($r);
        $objective = $input['lesson']['objectives'][0]['id'] ?? '';
        $title = (string) ($input['lesson']['title'] ?? 'Practice');
        $usable = array_values(array_filter($sentences, fn ($s) => self::pickWord($s['text']) !== null));
        $cited = array_values(array_unique(array_column($usable ?: $sentences, 'id')));
        $data = match ($library) {
            'H5P.DragText' => (function () use ($usable) {
                $take = array_slice($usable, 0, 3);
                $text = '';
                $words = [];
                foreach ($take as $i => $s) {
                    $word = self::pickWord($s['text']);
                    $text .= ($i > 0 ? ' ' : '') . preg_replace('/\\b' . preg_quote($word, '/') . '\\b/u', '{' . (count($words) + 1) . '}', $s['text'], 1);
                    $words[] = ['answer' => $word, 'tip' => ''];
                }

                return ['instruction' => 'Drag each word into the gap where it belongs.', 'text' => $text, 'words' => $words, 'distractors' => []];
            })(),
            'H5P.Dialogcards' => ['title' => mb_substr($title, 0, 100), 'instruction' => 'Turn each card and check your answer.', 'cards' => array_map(
                fn ($s, $i) => ['front' => 'What does the source say? (' . ($i + 1) . ')', 'back' => mb_substr($s['text'], 0, 500), 'tip' => ''],
                array_slice($sentences, 0, 3),
                array_keys(array_slice($sentences, 0, 3)),
            )],
            default => ['instruction' => 'Fill in the missing word in each sentence.', 'items' => array_map(function ($s) {
                $word = (string) self::pickWord($s['text']);

                return ['text' => preg_replace('/\\b' . preg_quote($word, '/') . '\\b/u', '{1}', $s['text'], 1), 'blanks' => [['answer' => $word, 'alternatives' => [], 'tip' => '']]];
            }, array_slice($usable, 0, 3))],
        };

        return ['interaction' => ['library' => $library, 'title' => 'Practice: ' . mb_substr($title, 0, 150), 'citations' => array_slice($cited, 0, 3) ?: [array_key_first(self::fragments($r))], 'objectiveIds' => [$objective], 'data' => $data]];
    }

    private static function interactive(DriverRequest $r): array
    {
        $input = self::input($r);
        $package = (array) ($input['library'][0] ?? []);
        $steps = array_column((array) ($package['steps'] ?? []), 'id');
        $sentences = self::lessonSentences($r);
        $ids = array_values(array_unique(array_column($sentences, 'id'))) ?: [array_key_first(self::fragments($r))];

        return ['interaction' => [
            'packageId' => (int) ($package['id'] ?? 0),
            'title' => (string) ($package['title'] ?? 'Interactive'),
            'startStep' => $steps[0] ?? '',
            'endStep' => $steps !== [] ? $steps[array_key_last($steps)] : '',
            'caption' => implode(' ', array_slice(array_column($sentences, 'text'), 0, 2)) ?: 'Explore the interactive and compare it with the lesson text.',
            'citations' => array_slice($ids, 0, 2),
            'objectiveIds' => [$input['lesson']['objectives'][0]['id'] ?? ''],
        ]];
    }

    private static function price(DriverRequest $r): array
    {
        $brief = (array) json_decode((string) self::tag($r, 'course_brief'), true);
        $minutes = (int) ($brief['totalMinutes'] ?? 60);

        return [
            'amountMinor' => max(900, (int) (round($minutes / 30) * 1000) - 100),
            'rationale' => sprintf('A %d-minute %s course with quizzes; comparable short courses are typically priced in this range.', $minutes, (string) ($brief['level'] ?? 'beginner')),
        ];
    }

    private static function metadata(DriverRequest $r): array
    {
        $outline = (array) json_decode((string) self::tag($r, 'approved_outline'), true);
        $title = mb_substr((string) ($outline['title'] ?? self::sourceTitle($r)), 0, 80);

        return [
            'title' => $title,
            'subtitle' => 'Learn ' . mb_strtolower($title) . ' step by step, from the source',
            'description' => 'A practical course built from your own material. Each lesson explains one part of the source and cites the passage it comes from. Short quizzes help you check what you learned.',
            'seoTitle' => mb_substr($title, 0, 60),
            'seoDescription' => mb_substr('A practical course on ' . mb_strtolower($title) . ', built from cited source material.', 0, 155),
            'faq' => [
                ['question' => 'Who is this course for?', 'answer' => 'Anyone who wants to learn the topic from the ground up.', 'citations' => []],
                ['question' => 'How long does it take?', 'answer' => 'The lessons are short; most learners finish in one sitting.', 'citations' => []],
                ['question' => 'Where does the content come from?', 'answer' => 'Every lesson cites the section of the source it is based on.', 'citations' => []],
            ],
        ];
    }

    private static function patch(DriverRequest $r): array
    {
        $input = self::input($r);
        $element = $input['element'] ?? [];
        $type = $element['type'] ?? 'block';
        unset($element['type'], $element['label']);
        $request = html_entity_decode((string) self::tag($r, 'author_request'), ENT_QUOTES | ENT_XML1);

        $replacement = match ($type) {
            'question' => [
                'id' => $element['id'],
                'type' => $element['type'] ?? 'single',
                'stem' => $element['stem'],
                'options' => array_map(fn ($o) => ['id' => $o['id'], 'text' => $o['correct'] ? $o['text'] : rtrim($o['text'], '.') . ', in most cases.', 'correct' => $o['correct']], $element['options']),
                'explanation' => rtrim($element['explanation']) . ' Compare the options carefully: only one matches the source exactly.',
                'citations' => $element['citations'],
                'objectiveIds' => $element['objectiveIds'],
            ],
            'block' => ['id' => $element['id'], 'kind' => $element['kind'], 'markdown' => rtrim($element['markdown']) . "\n\nIn short: " . (self::sentences($element['markdown'])[0] ?? 'see the cited section.'), 'citations' => $element['citations'], 'objectiveIds' => $element['objectiveIds']],
            'lesson' => ['title' => $element['title'], 'summary' => 'Revised: ' . ($element['summary'] ?: $element['title']), 'minutes' => $element['minutes'], 'blocks' => $element['blocks']],
            'module' => ['title' => $element['title'], 'summary' => 'Revised: ' . ($element['summary'] ?: $element['title'])],
            default => ['title' => $element['title'], 'subtitle' => $element['subtitle'] ?? '', 'description' => trim(($element['description'] ?? '') . ' Revised as requested.')],
        };

        return [
            'reply' => 'Here is a revised version' . ($request !== '' ? ' for: "' . mb_substr($request, 0, 120) . '"' : '') . '. Review the changes and approve them if they work for you.',
            'ui' => $type === 'question' ? 'QuizQuestionCard' : 'DiffView',
            'replacement' => $replacement,
        ];
    }
}
