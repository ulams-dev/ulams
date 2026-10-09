<?php

namespace Ulams\Interactive\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Opis\JsonSchema\Validator;
use Ulams\Courses\Models\Topic;
use Ulams\Interactive\Models\InteractiveTopic;

/**
 * The batch of bridge events a lesson page forwards for the logged-in learner. Access: the learner
 * must be able to attend the course of an Interactive topic (404 otherwise). Every event is checked
 * against the ulams-ix JSON Schema of its type (ADR 0087).
 */
class InteractiveEventsRequest extends FormRequest
{
    /** Message types the page may report; the rest of the protocol flows the other way. */
    public const TYPES = ['stepChanged', 'progress', 'complete', 'score', 'event'];

    private ?Topic $topicModel = null;

    public function authorize(): bool
    {
        abort_unless(config('ulams_interactive.enabled'), 404);
        $topic = $this->topic();
        abort_unless($topic !== null && $topic->topicable instanceof InteractiveTopic, 404);
        $course = $topic->lesson?->course;

        return $course !== null && Gate::forUser($this->user())->allows('attend', $course);
    }

    public function topic(): ?Topic
    {
        return $this->topicModel ??= Topic::query()->with(['topicable', 'lesson.course'])->find((int) $this->route('topic'));
    }

    public function rules(): array
    {
        $max = (int) config('ulams_interactive.events.max_batch', 40);

        return [
            'events' => ['required', 'array', 'min:1', 'max:' . $max],
            'events.*' => ['required', 'array', $this->eventRule()],
        ];
    }

    private function eventRule(): ValidationRule
    {
        return new class () implements ValidationRule {
            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                $type = $value['type'] ?? null;
                if (!is_string($type) || !in_array($type, InteractiveEventsRequest::TYPES, true)) {
                    $fail('The event type is not accepted here.');

                    return;
                }
                $schemaFile = __DIR__ . '/../../../resources/schemas/ulams-ix/v1/' . $type . '.json';
                // the page forwards the payload; the envelope (version, nonce) is the bridge's business
                $envelope = $value + ['ulams-ix' => 1, 'nonce' => 'forwarded'];
                $validator = new Validator();
                $validator->parser()->setOption('defaultDraft', '2020-12');
                $result = $validator->validate(json_decode((string) json_encode($envelope)), json_decode((string) file_get_contents($schemaFile)));
                if (!$result->isValid()) {
                    $fail("The {$type} event does not match the ulams-ix schema.");
                }
            }
        };
    }

    /** @return array<int, array<string, mixed>> */
    public function events(): array
    {
        return array_map(function (array $event) {
            unset($event['ulams-ix'], $event['nonce']);

            return $event;
        }, (array) $this->validated('events'));
    }
}
