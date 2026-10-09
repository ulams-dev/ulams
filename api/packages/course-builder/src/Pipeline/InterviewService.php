<?php

namespace Ulams\CourseBuilder\Pipeline;

use InvalidArgumentException;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Ui\Surfaces;
use Ulams\CourseBuilder\Ui\UiCatalogue;

/**
 * Stage 1: the adaptive interview. The light model reads the source and proposes one question per
 * brief field with options, a default justified by the source and the control that fits
 * (`ChoiceChips`, `SingleChoice`, `DurationSlider`, `LanguagePicker`). Code turns each choice into a
 * catalogue component and validates its props; an invalid choice falls back to text.
 */
final class InterviewService
{
    private const LANGUAGES = ['en' => 'English', 'pl' => 'Polski', 'de' => 'Deutsch', 'es' => 'Español', 'fr' => 'Français', 'it' => 'Italiano', 'pt' => 'Português', 'nl' => 'Nederlands', 'uk' => 'Українська', 'cs' => 'Čeština'];

    public function __construct(
        private readonly Llm $llm,
        private readonly PromptContext $context,
        private readonly BriefService $briefs,
        private readonly Surfaces $surfaces,
        private readonly UiCatalogue $catalogue,
        private readonly EventLog $events,
    ) {
    }

    public function start(Session $session, Run $run): void
    {
        $session->brief = $this->briefs->defaults($session);
        $result = $this->llm->generate($session, $run, 'interview', [
            $this->context->sourceBlock($session),
            $this->context->instruction('Ask the interview questions for this source.', ['sourceLanguage' => $session->brief['language']]),
        ], fn (array $data) => self::validate($data));

        $questions = array_map(fn ($q) => [
            'key' => $q['key'],
            'label' => $q['label'],
            'why' => $q['why'],
            'component' => $q['component'],
            'options' => $q['options'],
            'default' => $q['defaultValue'],
            'answered' => false,
        ], $result->data['questions']);
        $session->putState('interview', ['questions' => $questions, 'message' => $result->data['message']]);
        $session->status = Session::INTERVIEWING;
        $session->save();

        $this->events->text($session, $run, (string) $result->data['message']);
        $this->publish($session, $run);
    }

    /** @return string[] */
    public static function validate(array $data): array
    {
        $errors = [];
        $keys = array_column($data['questions'] ?? [], 'key');
        foreach (BriefService::KEYS as $key) {
            if (!in_array($key, $keys, true)) {
                $errors[] = "questions: missing the \"{$key}\" question";
            }
        }
        if (count($keys) !== count(array_unique($keys))) {
            $errors[] = 'questions: each key may appear once';
        }
        foreach ($data['questions'] ?? [] as $i => $q) {
            $values = array_column($q['options'] ?? [], 'value');
            $default = (string) ($q['defaultValue'] ?? '');
            $check = match ($q['key'] ?? '') {
                'duration' => fn () => BriefService::duration($default)[0] > 0,
                'assessments' => fn () => array_diff(array_filter(array_map('trim', explode(',', $default))), ['quiz', 'final']) === [],
                'audience' => fn () => $default !== '',
                default => fn () => in_array($default, $values, true),
            };
            if (!$check()) {
                $errors[] = "questions/{$i}: defaultValue \"{$default}\" does not fit the options";
            }
            if (($q['key'] ?? '') === 'level' && array_diff($values, ['beginner', 'intermediate', 'advanced']) !== []) {
                $errors[] = "questions/{$i}: level values are beginner, intermediate, advanced";
            }
            if (($q['key'] ?? '') === 'tone' && array_diff($values, ['friendly', 'professional', 'playful', 'academic']) !== []) {
                $errors[] = "questions/{$i}: tone values are friendly, professional, playful, academic";
            }
        }

        return $errors;
    }

    /**
     * Records an answer (or a default) and re-renders the interview.
     *
     * @return bool true when every question is answered
     */
    public function answer(Session $session, ?Run $run, string $key, mixed $value, bool $decideForMe = false): bool
    {
        $questions = $session->stateValue('interview.questions', []);
        $index = array_search($key, array_column($questions, 'key'), true);
        if ($index === false) {
            throw new InvalidArgumentException("Unknown question {$key}");
        }
        $value = $decideForMe ? $questions[$index]['default'] : $value;
        $session->brief = $this->briefs->answer($session, $key, $value, $decideForMe ? 'default' : 'author');
        $questions[$index]['answered'] = true;
        $session->putState('interview.questions', $questions);
        $session->save();
        $this->events->stateDelta($session, $run, [['op' => 'replace', 'path' => '/brief', 'value' => $session->brief]]);

        return $this->publish($session, $run) === 0;
    }

    /** Fills every open question with its default. */
    public function decideRest(Session $session, ?Run $run): void
    {
        foreach ($session->stateValue('interview.questions', []) as $q) {
            if (!$q['answered']) {
                $this->answer($session, $run, $q['key'], null, true);
            }
        }
    }

    /** Renders the interview surface; returns the number of open questions. */
    public function publish(Session $session, ?Run $run): int
    {
        $questions = $session->stateValue('interview.questions', []);
        $brief = $this->briefs->current($session);
        $firstOpen = null;
        foreach ($questions as $i => $q) {
            if (!$q['answered']) {
                $firstOpen = $i;
                break;
            }
        }
        $nodes = [];
        $open = 0;
        foreach ($questions as $i => $q) {
            $status = $q['answered'] ? 'answered' : ($i === $firstOpen ? 'open' : 'upcoming');
            $open += $q['answered'] ? 0 : 1;
            [$component, $props] = $this->control($q, $brief, $status, $i + 1, count($questions));
            $nodes[] = $this->catalogue->nodeOrFallback("q-{$q['key']}", $component, $props, "{$q['label']} (answer in your own words)", 'interview');
        }
        $this->surfaces->interview($session, $run, $nodes, $open);

        return $open;
    }

    /** @return array{0:string,1:array<string,mixed>} catalogue component and props for a question */
    private function control(array $q, array $brief, string $status, int $step, int $total): array
    {
        $base = [
            'questionKey' => $q['key'],
            'label' => $q['label'],
            'why' => $q['why'],
            'status' => $status,
            'step' => $step,
            'total' => $total,
        ];
        if (isset($brief['decidedBy'][self::field($q['key'])])) {
            $base['decidedBy'] = $brief['decidedBy'][self::field($q['key'])];
        }
        $options = array_slice($q['options'], 0, 6);
        $component = $q['component'];
        // the language control always lists known languages with the model's suggestions first
        if ($q['key'] === 'language') {
            $component = 'LanguagePicker';
            $codes = array_values(array_unique([...array_map(fn ($o) => strtolower(substr((string) $o['value'], 0, 2)), $options), ...array_keys(self::LANGUAGES)]));
            $options = array_map(fn ($c) => ['value' => $c, 'label' => self::LANGUAGES[$c] ?? strtoupper($c)], array_slice($codes, 0, 12));
        }
        if ($q['key'] === 'duration') {
            $component = 'DurationSlider';
            [$total, $lesson] = BriefService::duration($q['default']);
            $presets = array_values(array_unique(array_filter([...array_map(fn ($o) => (int) $o['value'], $options), $total])));
            sort($presets);

            return [$component, $base + [
                'totalOptions' => array_slice($presets, 0, 6),
                'lessonOptions' => [5, 10, 15, 20],
                'defaultValue' => ['totalMinutes' => $total, 'lessonMinutes' => $lesson],
            ] + ($q['answered'] ? ['value' => ['totalMinutes' => (int) $brief['totalMinutes'], 'lessonMinutes' => (int) $brief['lessonMinutes']]] : [])];
        }
        $answered = $q['answered'] ? self::answerValue($q['key'], $brief) : null;
        if ($component === 'ChoiceChips' || $q['key'] === 'assessments') {
            $multiple = $q['key'] === 'assessments';
            $default = $multiple ? array_values(array_filter(array_map('trim', explode(',', (string) $q['default'])))) : [(string) $q['default']];

            return ['ChoiceChips', $base + [
                'options' => $options,
                'multiple' => $multiple,
                'allowCustom' => $q['key'] === 'audience',
                'defaultValue' => $default,
            ] + ($answered !== null ? ['value' => array_map(fn ($v) => mb_substr((string) $v, 0, 120), (array) $answered)] : [])];
        }

        return [$component, $base + [
            'options' => $options,
            'defaultValue' => (string) $q['default'],
        ] + ($answered !== null ? ['value' => (string) (is_array($answered) ? ($answered[0] ?? '') : $answered)] : [])];
    }

    private static function field(string $key): string
    {
        return match ($key) {
            'duration' => 'totalMinutes',
            default => $key,
        };
    }

    private static function answerValue(string $key, array $brief): mixed
    {
        return match ($key) {
            'assessments' => array_values(array_filter([
                !empty($brief['assessments']['perLessonQuiz']) ? 'quiz' : null,
                !empty($brief['assessments']['finalTest']) ? 'final' : null,
            ])),
            default => $brief[$key] ?? null,
        };
    }
}
