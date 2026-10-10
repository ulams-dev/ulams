<?php

namespace Ulams\CourseBuilder\Quality;

use Throwable;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\ContentTypes\ContentTypeRegistry;
use Ulams\CourseBuilder\ContentTypes\H5pLibraries;
use Ulams\CourseBuilder\ContentTypes\LiaScriptRenderer;

/**
 * Deterministic mechanics of a lesson (ADR 0051): one correct answer for single choice, distinct
 * options, an explanation on every question, and the format-specific parts (the LiaScript text and the
 * H5P parameters) build without errors.
 */
final class MechanicsCritic
{
    public function __construct(private readonly ContentTypeRegistry $types)
    {
    }

    /** @return array<int,array{elementId:string,problem:string}> */
    public function check(array $lesson): array
    {
        $issues = [];
        $add = function (string $id, string $problem) use (&$issues): void {
            $issues[] = ['elementId' => $id, 'problem' => $problem];
        };
        foreach (['selfChecks' => 'Self-check', 'quiz' => 'Quiz question'] as $key => $label) {
            $questions = $key === 'quiz' ? ($lesson['quiz']['questions'] ?? []) : ($lesson[$key] ?? []);
            foreach ($questions as $i => $q) {
                $where = "{$label} " . ($i + 1);
                foreach (Checks::questionShape($q, $where) as $error) {
                    $add($q['id'], $error);
                }
                if (trim((string) ($q['explanation'] ?? '')) === '') {
                    $add($q['id'], "{$where}: has no explanation");
                }
                $texts = array_map(fn ($o) => mb_strtolower(trim((string) $o['text'])), $q['options'] ?? []);
                if (count(array_unique($texts)) !== count($texts)) {
                    $add($q['id'], "{$where}: two options have the same text");
                }
            }
        }
        $type = $this->types->forLesson($lesson);
        if ($type->key() === 'liascript') {
            foreach ($lesson['blocks'] ?? [] as $b) {
                if (LiaScriptRenderer::unsafe((string) $b['markdown'])) {
                    $add($b['id'], 'The text contains LiaScript code or an import');
                }
            }
            try {
                $text = $type->topics($lesson, [], '')[0]['data']['markdown'] ?? '';
                if (trim((string) $text) === '') {
                    $add($lesson['id'], 'The LiaScript lesson renders empty');
                }
            } catch (Throwable $e) {
                $add($lesson['id'], 'The LiaScript lesson does not render: ' . $e->getMessage());
            }
        }
        $interaction = $lesson['interaction'] ?? null;
        if (is_array($interaction) && ($interaction['kind'] ?? '') === 'h5p') {
            $errors = H5pLibraries::errors((string) $interaction['library'], (array) $interaction['data'], 'The H5P activity');
            try {
                H5pLibraries::params((string) $interaction['library'], (array) $interaction['data']);
            } catch (Throwable $e) {
                $errors[] = 'The H5P activity does not build: ' . $e->getMessage();
            }
            foreach ($errors as $error) {
                $add($interaction['id'], $error);
            }
        }
        if (in_array($lesson['contentType'] ?? 'richtext', ['h5p', 'interactive'], true) && !is_array($interaction)) {
            $add($lesson['id'], 'The lesson is set up for an activity but has none');
        }

        return $issues;
    }
}
