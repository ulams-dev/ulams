<?php

namespace Ulams\CodeExercises\Models;

use Illuminate\Support\Carbon;
use Ulams\CodeExercises\Enums\CompletionRule;
use Ulams\CodeExercises\Rules\ValidExerciseFiles;
use Ulams\TopicTypes\Models\TopicContent\AbstractTopicContent;

/**
 * @OA\Schema(
 *      schema="TopicCodeExercise",
 *      required={"language", "files", "visible_tests"},
 *      @OA\Property(property="id", type="integer"),
 *      @OA\Property(property="language", type="string", enum={"javascript","typescript"}),
 *      @OA\Property(property="files", type="array", description="Starter files (a JSON string is accepted in multipart requests): at most 20, 64 KB each, 256 KB together", @OA\Items(type="object", @OA\Property(property="path", type="string"), @OA\Property(property="content", type="string"))),
 *      @OA\Property(property="visible_tests", type="array", description="Test files the learner can read and run in the browser", @OA\Items(type="object", @OA\Property(property="path", type="string"), @OA\Property(property="content", type="string"))),
 *      @OA\Property(property="timeout_ms", type="integer", description="A run is stopped after this long (500 to 30000)"),
 *      @OA\Property(property="completion_rule", type="string", enum={"on_pass","on_attempt"}),
 *      @OA\Property(property="pass_score", type="integer", description="Percentage of passing tests that completes the topic (on_pass)"),
 *      @OA\Property(property="text", type="string", nullable=true, description="The task, in Markdown")
 * )
 *
 * Topic type: a code exercise the learner edits and runs in the browser sandbox (ADR 0170). Results
 * are practice grades, reported by the learner's browser: they are stored as unverified attempts.
 *
 * @property int $id
 * @property string $language
 * @property string $runtime
 * @property string $grading
 * @property array<int, array{path: string, content: string}> $files
 * @property array<int, array{path: string, content: string}> $visible_tests
 * @property int $timeout_ms
 * @property string $completion_rule
 * @property int $pass_score
 * @property ?string $text
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CodeExerciseTopic extends AbstractTopicContent
{
    public $table = 'topic_code_exercises';

    protected $fillable = ['language', 'files', 'visible_tests', 'timeout_ms', 'completion_rule', 'pass_score', 'text'];

    protected $casts = [
        'id' => 'integer',
        'files' => 'array',
        'visible_tests' => 'array',
        'timeout_ms' => 'integer',
        'pass_score' => 'integer',
    ];

    protected $attributes = [
        'runtime' => 'browser',
        'grading' => 'practice',
        'timeout_ms' => 5000,
        'completion_rule' => 'on_pass',
        'pass_score' => 100,
    ];

    public static function rules(): array
    {
        $timeout = (array) config('ulams_code_exercises.limits.timeout_ms');

        return [
            'language' => ['required', 'string', 'in:' . implode(',', array_keys((array) config('ulams_code_exercises.languages')))],
            'files' => ['required', new ValidExerciseFiles(false)],
            'visible_tests' => ['required', new ValidExerciseFiles(true)],
            'timeout_ms' => ['sometimes', 'integer', 'between:' . $timeout['min'] . ',' . $timeout['max']],
            'completion_rule' => ['sometimes', 'in:' . implode(',', array_column(CompletionRule::cases(), 'value'))],
            'pass_score' => ['sometimes', 'integer', 'between:1,100'],
            'text' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /** The admin sends lists as JSON strings inside multipart form data; they are stored as JSON either way. */
    public function setFilesAttribute(mixed $value): void
    {
        $this->attributes['files'] = $this->encodeFiles($value);
    }

    public function setVisibleTestsAttribute(mixed $value): void
    {
        $this->attributes['visible_tests'] = $this->encodeFiles($value);
    }

    private function encodeFiles(mixed $value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : $value;
        }
        // only path and content are kept, in this order
        if (is_array($value)) {
            $value = array_map(fn ($f) => is_array($f) ? ['path' => $f['path'] ?? null, 'content' => $f['content'] ?? null] : $f, $value);
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** The exercise lives in its table: there are no files to move on a course export. */
    public function fixAssetPaths(): array
    {
        return [];
    }

    /** Files of the exercise as the runner receives them: starter files and visible tests. */
    public function runnerPayload(): array
    {
        return [
            'language' => $this->language,
            'files' => array_values($this->files ?? []),
            'tests' => array_values($this->visible_tests ?? []),
            'timeout_ms' => $this->timeout_ms,
        ];
    }
}
