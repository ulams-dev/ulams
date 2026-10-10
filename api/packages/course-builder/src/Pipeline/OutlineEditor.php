<?php

namespace Ulams\CourseBuilder\Pipeline;

use InvalidArgumentException;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\ContentTypes\ContentTypeRegistry;

/**
 * The author's direct edits of the course structure (L2-14): reorder, move, rename, add and remove
 * modules and lessons. Each action is a pure function of the document; the caller stores the result
 * as an approved author version (no model call, no approval). Objectives, citations, blocks and quizzes
 * are part of the node they belong to, so they travel with it. Elements keep their ids, so the apply
 * updates the LMS in place.
 */
final class OutlineEditor
{
    public const ACTIONS = ['rename', 'move', 'add', 'remove', 'set_format'];

    public function __construct(private readonly ContentTypeRegistry $types)
    {
    }

    /**
     * @param array<string,mixed> $action {action, id, ...}
     * @param array<string,bool> $known fragment ids of the session (for `add`)
     * @return array{0:array,1:string} the new document and a sentence describing the change
     */
    public function apply(array $doc, array $action, array $known = []): array
    {
        $name = (string) ($action['action'] ?? '');

        return match ($name) {
            'rename' => $this->rename($doc, (string) ($action['id'] ?? ''), trim((string) ($action['title'] ?? ''))),
            'move' => $this->move($doc, (string) ($action['id'] ?? ''), $action),
            'add' => $this->add($doc, $action, $known),
            'remove' => $this->remove($doc, (string) ($action['id'] ?? '')),
            'set_format' => $this->format($doc, (string) ($action['id'] ?? ''), (string) ($action['contentType'] ?? '')),
            default => throw new InvalidArgumentException('Unknown outline action.'),
        };
    }

    private function title(string $title): string
    {
        if (mb_strlen($title) < 2 || mb_strlen($title) > 200) {
            throw new InvalidArgumentException('A title needs 2–200 characters.');
        }
        if (Checks::markup($title, 'title') !== []) {
            throw new InvalidArgumentException('Titles are plain text.');
        }

        return $title;
    }

    /** @return array{0:string,1:int,2:?int} kind (module|lesson), module index, lesson index */
    private function locate(array $doc, string $id): array
    {
        foreach ($doc['modules'] as $m => $module) {
            if ($module['id'] === $id) {
                return ['module', $m, null];
            }
            foreach ($module['lessons'] as $l => $lesson) {
                if ($lesson['id'] === $id) {
                    return ['lesson', $m, $l];
                }
            }
        }
        throw new InvalidArgumentException('That module or lesson is not in the course.');
    }

    private function rename(array $doc, string $id, string $title): array
    {
        $title = $this->title($title);
        [$kind, $m, $l] = $this->locate($doc, $id);
        if ($kind === 'module') {
            $doc['modules'][$m]['title'] = $title;
        } else {
            $doc['modules'][$m]['lessons'][$l]['title'] = $title;
        }

        return [$doc, "Renamed the {$kind} to “{$title}”."];
    }

    /**
     * `move {id, moduleId?, index}`: a lesson to a position in a module (default its own), a module to a position among the modules.
     * Indexes are 0-based positions in the target list after the element is taken out.
     */
    private function move(array $doc, string $id, array $action): array
    {
        [$kind, $m, $l] = $this->locate($doc, $id);
        $index = (int) ($action['index'] ?? 0);
        if ($kind === 'module') {
            $node = $doc['modules'][$m];
            array_splice($doc['modules'], $m, 1);
            $index = max(0, min($index, count($doc['modules'])));
            array_splice($doc['modules'], $index, 0, [$node]);

            return [$doc, 'Moved module “' . $node['title'] . '” to position ' . ($index + 1) . '.'];
        }
        $lesson = $doc['modules'][$m]['lessons'][$l];
        $targetId = (string) ($action['moduleId'] ?? $doc['modules'][$m]['id']);
        $target = null;
        foreach ($doc['modules'] as $i => $module) {
            if ($module['id'] === $targetId) {
                $target = $i;
            }
        }
        if ($target === null) {
            throw new InvalidArgumentException('That module is not in the course.');
        }
        if ($target !== $m && count($doc['modules'][$m]['lessons']) === 1) {
            throw new InvalidArgumentException('A module needs at least one lesson. Move another lesson in first, or remove the module.');
        }
        array_splice($doc['modules'][$m]['lessons'], $l, 1);
        $index = max(0, min($index, count($doc['modules'][$target]['lessons'])));
        array_splice($doc['modules'][$target]['lessons'], $index, 0, [$lesson]);

        return [$doc, 'Moved lesson “' . $lesson['title'] . '” to ' . ($target === $m ? 'position ' . ($index + 1) : 'module “' . $doc['modules'][$target]['title'] . '”') . '.'];
    }

    /**
     * `add {kind: lesson|module, moduleId?, title, objective, citations[], minutes?}`. A lesson needs an objective and at
     * least one source section to rest on, like every lesson; it starts as planned and is written by asking the assistant.
     */
    private function add(array $doc, array $action, array $known): array
    {
        $title = $this->title(trim((string) ($action['title'] ?? '')));
        $objective = trim((string) ($action['objective'] ?? ''));
        if ($objective === '' || mb_strlen($objective) > 400 || Checks::markup($objective, 'objective') !== []) {
            throw new InvalidArgumentException('Give the lesson one plain-text learning objective (up to 400 characters).');
        }
        $citations = array_values(array_unique(array_map('strval', (array) ($action['citations'] ?? []))));
        $errors = Checks::citations($citations, $known, 'lesson');
        if ($errors !== []) {
            throw new InvalidArgumentException('Pick at least one section of your source for the lesson to rest on.');
        }
        $lesson = [
            'id' => Blueprint::newId(),
            'title' => $title,
            'minutes' => max(1, min(600, (int) ($action['minutes'] ?? 5))),
            'objectives' => [['id' => Blueprint::newId(), 'text' => $objective, 'citations' => $citations]],
            'citations' => $citations,
            'contentType' => 'richtext',
            'status' => 'planned',
            'blocks' => [],
            'quiz' => null,
            'flags' => [],
        ];
        if (($action['kind'] ?? 'lesson') === 'module') {
            $doc['modules'][] = ['id' => Blueprint::newId(), 'title' => $title, 'lessons' => [$lesson]];

            return [$doc, "Added module “{$title}” with its first lesson."];
        }
        $moduleId = (string) ($action['moduleId'] ?? '');
        foreach ($doc['modules'] as $m => $module) {
            if ($module['id'] === $moduleId) {
                $doc['modules'][$m]['lessons'][] = $lesson;

                return [$doc, "Added lesson “{$title}” to module “{$module['title']}”. It has no text yet: ask the assistant to write it."];
            }
        }
        throw new InvalidArgumentException('Choose the module the lesson goes into.');
    }

    private function remove(array $doc, string $id): array
    {
        [$kind, $m, $l] = $this->locate($doc, $id);
        if ($kind === 'module') {
            if (count($doc['modules']) === 1) {
                throw new InvalidArgumentException('A course needs at least one module.');
            }
            $title = $doc['modules'][$m]['title'];
            array_splice($doc['modules'], $m, 1);

            return [$doc, "Removed module “{$title}” and its lessons."];
        }
        if (count($doc['modules'][$m]['lessons']) === 1) {
            if (count($doc['modules']) === 1) {
                throw new InvalidArgumentException('A course needs at least one lesson.');
            }
            throw new InvalidArgumentException('A module needs at least one lesson. Remove the module instead.');
        }
        $title = $doc['modules'][$m]['lessons'][$l]['title'];
        array_splice($doc['modules'][$m]['lessons'], $l, 1);

        return [$doc, "Removed lesson “{$title}”."];
    }

    private function format(array $doc, string $id, string $key): array
    {
        [$kind, $m, $l] = $this->locate($doc, $id);
        if ($kind !== 'lesson' || !isset($this->types->enabled()[$key])) {
            throw new InvalidArgumentException('That lesson format is not available here.');
        }
        $lesson = $doc['modules'][$m]['lessons'][$l];
        if (($lesson['status'] ?? '') === 'generated' && $key !== 'richtext' && $key !== ($lesson['contentType'] ?? 'richtext')) {
            throw new InvalidArgumentException('A written lesson can only go back to rich text; its other formats are written together with the lesson.');
        }
        $doc['modules'][$m]['lessons'][$l]['contentType'] = $key;
        if ($key === 'richtext') {
            unset($doc['modules'][$m]['lessons'][$l]['selfChecks']);
            $doc['modules'][$m]['lessons'][$l]['interaction'] = null;
        }

        return [$doc, 'Set the format of lesson “' . $lesson['title'] . '” to ' . $this->types->for($key)->label() . '.'];
    }
}
