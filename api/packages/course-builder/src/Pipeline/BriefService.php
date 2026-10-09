<?php

namespace Ulams\CourseBuilder\Pipeline;

use InvalidArgumentException;
use Ulams\CourseBuilder\Blueprint\SchemaRegistry;
use Ulams\CourseBuilder\Models\Session;

/**
 * The Course Brief: interview answers, schema-validated (`course-brief/v1`), each field marked as
 * decided by the author or by default. Interview question keys map onto brief fields here.
 */
final class BriefService
{
    public const KEYS = ['audience', 'level', 'duration', 'tone', 'assessments', 'language'];

    public function __construct(private readonly SchemaRegistry $schemas)
    {
    }

    /** Defaults from the source alone (used before the interview and for "decide for me"). */
    public function defaults(Session $session): array
    {
        $tokens = (int) $session->sources()->sum('token_estimate');
        $language = (string) ($session->sources()->first()?->metadata['language'] ?? 'en');
        // about 1 minute of learning per 250 tokens of source, rounded to presets
        $total = $tokens < 4000 ? 30 : ($tokens < 12000 ? 60 : ($tokens < 40000 ? 120 : 240));

        return [
            'audience' => 'Adults new to the topic',
            'level' => 'beginner',
            'totalMinutes' => $total,
            'lessonMinutes' => 10,
            'tone' => 'friendly',
            'assessments' => ['perLessonQuiz' => true, 'finalTest' => false, 'passScore' => 70],
            'language' => preg_match('/^[a-z]{2}$/', $language) ? $language : 'en',
            'decidedBy' => [],
        ];
    }

    public function current(Session $session): array
    {
        return $session->brief ?? $this->defaults($session);
    }

    /**
     * Applies one interview answer. `$value` is what the control sent back.
     *
     * @throws InvalidArgumentException when the value does not fit the brief schema
     */
    public function answer(Session $session, string $key, mixed $value, string $decidedBy): array
    {
        $brief = $this->current($session);
        switch ($key) {
            case 'audience':
                $text = trim(is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value);
                $brief['audience'] = mb_substr($text, 0, 200);
                $fields = ['audience'];
                break;
            case 'level':
                $brief['level'] = (string) (is_array($value) ? ($value[0] ?? '') : $value);
                $fields = ['level'];
                break;
            case 'duration':
                [$total, $lesson] = self::duration($value, $brief['lessonMinutes'] ?? 10);
                $brief['totalMinutes'] = $total;
                $brief['lessonMinutes'] = $lesson;
                $fields = ['totalMinutes', 'lessonMinutes'];
                break;
            case 'tone':
                $brief['tone'] = (string) (is_array($value) ? ($value[0] ?? '') : $value);
                $fields = ['tone'];
                break;
            case 'assessments':
                $list = is_array($value) ? $value : array_filter(array_map('trim', explode(',', (string) $value)));
                $brief['assessments']['perLessonQuiz'] = in_array('quiz', $list, true);
                $brief['assessments']['finalTest'] = in_array('final', $list, true);
                $fields = ['assessments'];
                break;
            case 'language':
                $brief['language'] = strtolower(substr((string) (is_array($value) ? ($value[0] ?? '') : $value), 0, 2));
                $fields = ['language'];
                break;
            default:
                throw new InvalidArgumentException("Unknown brief field {$key}");
        }
        foreach ($fields as $field) {
            $brief['decidedBy'][$field] = $decidedBy;
        }
        $this->assertValid($brief);

        return $brief;
    }

    /** @return array{0:int,1:int} total and lesson minutes from "60|10", "60", 60 or an object */
    public static function duration(mixed $value, int $lessonDefault = 10): array
    {
        if (is_array($value) && isset($value['totalMinutes'])) {
            return [(int) $value['totalMinutes'], (int) ($value['lessonMinutes'] ?? $lessonDefault)];
        }
        $parts = explode('|', (string) (is_array($value) ? ($value[0] ?? '') : $value));

        return [(int) $parts[0], isset($parts[1]) && (int) $parts[1] > 0 ? (int) $parts[1] : $lessonDefault];
    }

    public function assertValid(array $brief): void
    {
        $errors = $this->schemas->validate('course-brief/v1', $brief);
        if ($errors !== []) {
            throw new InvalidArgumentException('Invalid brief: ' . implode('; ', array_slice($errors, 0, 5)));
        }
    }

    /** Readable summary rows for the brief panel. */
    public static function rows(array $brief): array
    {
        $assess = array_filter([
            !empty($brief['assessments']['perLessonQuiz']) ? 'Quiz after each lesson' : null,
            !empty($brief['assessments']['finalTest']) ? 'Final test' : null,
        ]);

        return [
            ['key' => 'audience', 'label' => 'Audience', 'value' => $brief['audience'] ?? ''],
            ['key' => 'level', 'label' => 'Level', 'value' => ucfirst((string) ($brief['level'] ?? ''))],
            ['key' => 'duration', 'label' => 'Duration', 'value' => sprintf('%d min · %d-min lessons', $brief['totalMinutes'] ?? 0, $brief['lessonMinutes'] ?? 0)],
            ['key' => 'tone', 'label' => 'Tone', 'value' => ucfirst((string) ($brief['tone'] ?? ''))],
            ['key' => 'assessments', 'label' => 'Assessments', 'value' => $assess ? implode(', ', $assess) : 'None'],
            ['key' => 'language', 'label' => 'Language', 'value' => strtoupper((string) ($brief['language'] ?? ''))],
        ];
    }
}
