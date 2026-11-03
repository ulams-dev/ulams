<?php

namespace Ulams\CodeExercises\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Ulams\CodeExercises\Models\CodeExerciseTopic;
use Ulams\Courses\Models\Topic;

/**
 * The result of a run, reported by the lesson page for the logged-in learner (404 unless the topic is a
 * code exercise the learner may attend). The body comes from the learner's browser, so every field is
 * bounded; the score is recomputed from `results` on the server.
 */
class AttemptRequest extends FormRequest
{
    private ?Topic $topicModel = null;

    public function authorize(): bool
    {
        abort_unless(config('ulams_code_exercises.enabled'), 404);
        $topic = $this->topic();
        abort_unless($topic !== null && $topic->topicable instanceof CodeExerciseTopic, 404);
        $course = $topic->lesson?->course;

        return $course !== null && Gate::forUser($this->user())->allows('attend', $course);
    }

    public function topic(): ?Topic
    {
        return $this->topicModel ??= Topic::query()->with(['topicable', 'lesson.course'])->find((int) $this->route('topic'));
    }

    public function exercise(): CodeExerciseTopic
    {
        /** @var CodeExerciseTopic $exercise */
        $exercise = $this->topic()?->topicable;

        return $exercise;
    }

    public function rules(): array
    {
        $limits = (array) config('ulams_code_exercises.limits');

        return [
            'results' => ['required', 'array', 'min:1', 'max:' . $limits['max_results']],
            'results.*' => ['required', 'array:name,status,message'],
            'results.*.name' => ['required', 'string', 'min:1', 'max:' . $limits['max_test_name']],
            'results.*.status' => ['required', 'in:pass,fail,error'],
            'results.*.message' => ['nullable', 'string', 'max:' . $limits['max_message']],
            'files_hash' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'runner' => ['nullable', 'string', 'regex:/^[a-z0-9][a-z0-9:._-]{0,39}$/'],
            'duration_ms' => ['nullable', 'integer', 'between:0,3600000'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function results(): array
    {
        return (array) $this->validated('results');
    }

    /** @return array{files_hash: ?string, runner: ?string, duration_ms: ?int} */
    public function meta(): array
    {
        return [
            'files_hash' => $this->validated('files_hash'),
            'runner' => $this->validated('runner'),
            'duration_ms' => $this->validated('duration_ms') === null ? null : (int) $this->validated('duration_ms'),
        ];
    }
}
