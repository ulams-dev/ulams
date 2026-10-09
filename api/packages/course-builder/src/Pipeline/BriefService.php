<?php

namespace Ulams\CourseBuilder\Pipeline;

use InvalidArgumentException;
use Ulams\CourseBuilder\Blueprint\SchemaRegistry;
use Ulams\CourseBuilder\Models\Session;

/**
 * The Course Brief: interview answers, schema-validated (`course-brief/v2`; v1 documents are read as v2), each field marked as
 * decided by the author or by default. Interview question keys map onto brief fields here.
 */
final class BriefService
{
    /** Questions the model asks (one each, in this order). */
    public const KEYS = ['audience', 'level', 'duration', 'tone', 'assessments', 'language'];

    /** Questions code adds after the model's: fixed options, no model call. */
    public const EXTRA_KEYS = ['pricing', 'theme'];

    public const THEMES = ['coffee', 'oncall', 'nightsky'];

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
        return self::upgrade($session->brief ?? $this->defaults($session));
    }

    /**
     * Reads a brief of any schema version as v2 without rewriting stored data: a v1 brief has no
     * pricing, which means free, and no theme or site, which means "keep the site as it is".
     */
    public static function upgrade(array $brief): array
    {
        $brief['pricing'] ??= ['mode' => 'free'];

        return $brief;
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
            case 'pricing':
                $brief['pricing'] = self::pricing($value);
                $fields = ['pricing'];
                break;
            case 'theme':
                $brief['theme'] = self::theme($value);
                $fields = ['theme'];
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

    /**
     * @return array{mode:string,amountMinor?:int,currency?:string} from "free", "paid" or an object
     *
     * @throws InvalidArgumentException
     */
    public static function pricing(mixed $value): array
    {
        $mode = is_array($value) ? (string) ($value['mode'] ?? '') : strtolower(trim((string) $value));
        if ($mode === 'free') {
            return ['mode' => 'free'];
        }
        if ($mode !== 'paid') {
            throw new InvalidArgumentException('Pricing is "free" or "paid".');
        }
        $pricing = ['mode' => 'paid'];
        $amount = is_array($value) ? ($value['amountMinor'] ?? null) : null;
        if ($amount !== null && $amount !== '') {
            if (!is_numeric($amount) || (int) $amount < 1) {
                throw new InvalidArgumentException('The price must be a positive amount.');
            }
            $pricing['amountMinor'] = (int) $amount;
            $pricing['currency'] = strtoupper((string) ($value['currency'] ?? config('ulams_payments.default_currency', 'USD')));
        }

        return $pricing;
    }

    /** @return array{preset:string,accent?:string} from a preset name or an object */
    public static function theme(mixed $value): array
    {
        $preset = is_array($value) ? (string) ($value['preset'] ?? '') : (string) $value;
        if (!in_array($preset, self::THEMES, true)) {
            throw new InvalidArgumentException('Theme is one of: ' . implode(', ', self::THEMES) . '.');
        }
        $theme = ['preset' => $preset];
        $accent = is_array($value) ? trim((string) ($value['accent'] ?? '')) : '';
        if ($accent !== '') {
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
                throw new InvalidArgumentException('The accent is a colour like #c2552d.');
            }
            $theme['accent'] = strtolower($accent);
        }

        return $theme;
    }

    /** @return array{mode:string,slug?:string} */
    public static function site(mixed $value): array
    {
        $mode = is_array($value) ? (string) ($value['mode'] ?? '') : (string) $value;
        if (!in_array($mode, ['current', 'new'], true)) {
            throw new InvalidArgumentException('Site is "current" or "new".');
        }
        if ($mode === 'current') {
            return ['mode' => 'current'];
        }
        $slug = strtolower(trim((string) (is_array($value) ? ($value['slug'] ?? '') : '')));
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,38}[a-z0-9]$/', $slug)) {
            throw new InvalidArgumentException('Give the new site a name of 3 to 40 letters, digits or dashes.');
        }

        return ['mode' => 'new', 'slug' => $slug];
    }

    public function assertValid(array $brief): void
    {
        $errors = $this->schemas->validate('course-brief/v2', $brief);
        if ($errors !== []) {
            throw new InvalidArgumentException('Invalid brief: ' . implode('; ', array_slice($errors, 0, 5)));
        }
    }

    public static function priceLabel(array $pricing): string
    {
        if (($pricing['mode'] ?? 'free') !== 'paid') {
            return 'Free';
        }

        return isset($pricing['amountMinor'])
            ? sprintf('%s %s', number_format($pricing['amountMinor'] / 100, 2, '.', ''), $pricing['currency'] ?? 'USD')
            : 'Paid (price to confirm)';
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
            ['key' => 'pricing', 'label' => 'Price', 'value' => self::priceLabel($brief['pricing'] ?? ['mode' => 'free'])],
            ...(isset($brief['site']) ? [['key' => 'site', 'label' => 'Site', 'value' => ($brief['site']['mode'] ?? 'current') === 'new' ? 'New site: ' . ($brief['site']['slug'] ?? '') : 'This site']] : []),
            ...(isset($brief['theme']) ? [['key' => 'theme', 'label' => 'Theme', 'value' => ucfirst($brief['theme']['preset']) . (isset($brief['theme']['accent']) ? ' · ' . $brief['theme']['accent'] : '')]] : []),
        ];
    }
}
