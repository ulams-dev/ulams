<?php

namespace Ulams\CourseBuilder\Blueprint;

use Illuminate\Support\Str;

/**
 * Helpers over a Course Blueprint document (a plain array, schema v2; v1 documents read as v2 with
 * only the version number changed). Element ids are ULIDs assigned here, never by the model.
 */
final class Blueprint
{
    public const SCHEMA_VERSION = 2;

    /** v1 → v2 changes no data: v2 only allows more lesson content types and the optional selfChecks and interaction. */
    public static function upgrade(array $doc): array
    {
        if ((int) ($doc['schemaVersion'] ?? 1) < self::SCHEMA_VERSION) {
            $doc['schemaVersion'] = self::SCHEMA_VERSION;
        }

        return $doc;
    }

    public static function newId(): string
    {
        return strtolower((string) Str::ulid());
    }

    public static function isId(mixed $value): bool
    {
        return is_string($value) && (bool) preg_match('/^[0-9a-z]{26}$/', $value);
    }

    /** @return array<string,mixed> */
    public static function empty(string $title, string $language): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'sources' => [],
            'course' => ['id' => self::newId(), 'title' => $title, 'language' => $language, 'objectives' => []],
            'modules' => [],
            'finalTest' => null,
            'pages' => ['landing' => null, 'header' => null],
        ];
    }

    /**
     * Every lesson with its position.
     *
     * @return \Generator<int,array{module:array,moduleIndex:int,lesson:array,lessonIndex:int,number:string}>
     */
    public static function lessons(array $doc): \Generator
    {
        foreach ($doc['modules'] ?? [] as $m => $module) {
            foreach ($module['lessons'] ?? [] as $l => $lesson) {
                yield ['module' => $module, 'moduleIndex' => $m, 'lesson' => $lesson, 'lessonIndex' => $l, 'number' => ($m + 1) . '.' . ($l + 1)];
            }
        }
    }

    /**
     * Finds an element anywhere in the document.
     *
     * @return array{type:string,path:array<int,string|int>,node:array,label:string,lessonId:?string}|null
     */
    public static function find(array $doc, string $id): ?array
    {
        if (($doc['course']['id'] ?? null) === $id) {
            return ['type' => 'course', 'path' => ['course'], 'node' => $doc['course'], 'label' => 'Course', 'lessonId' => null];
        }
        foreach ($doc['modules'] ?? [] as $m => $module) {
            $mLabel = 'Module ' . ($m + 1);
            if ($module['id'] === $id) {
                return ['type' => 'module', 'path' => ['modules', $m], 'node' => $module, 'label' => $mLabel, 'lessonId' => null];
            }
            foreach ($module['lessons'] ?? [] as $l => $lesson) {
                $lLabel = 'Lesson ' . ($m + 1) . '.' . ($l + 1);
                $base = ['modules', $m, 'lessons', $l];
                if ($lesson['id'] === $id) {
                    return ['type' => 'lesson', 'path' => $base, 'node' => $lesson, 'label' => $lLabel, 'lessonId' => $lesson['id']];
                }
                foreach ($lesson['blocks'] ?? [] as $b => $block) {
                    if ($block['id'] === $id) {
                        return ['type' => 'block', 'path' => [...$base, 'blocks', $b], 'node' => $block, 'label' => "{$lLabel} › block " . ($b + 1), 'lessonId' => $lesson['id']];
                    }
                }
                foreach ($lesson['selfChecks'] ?? [] as $c => $check) {
                    if ($check['id'] === $id) {
                        return ['type' => 'question', 'path' => [...$base, 'selfChecks', $c], 'node' => $check, 'label' => "{$lLabel} › check " . ($c + 1), 'lessonId' => $lesson['id']];
                    }
                }
                if (is_array($lesson['interaction'] ?? null) && $lesson['interaction']['id'] === $id) {
                    return ['type' => 'interaction', 'path' => [...$base, 'interaction'], 'node' => $lesson['interaction'], 'label' => "{$lLabel} › activity", 'lessonId' => $lesson['id']];
                }
                $quiz = $lesson['quiz'] ?? null;
                if (is_array($quiz)) {
                    if ($quiz['id'] === $id) {
                        return ['type' => 'quiz', 'path' => [...$base, 'quiz'], 'node' => $quiz, 'label' => "{$lLabel} › quiz", 'lessonId' => $lesson['id']];
                    }
                    foreach ($quiz['questions'] ?? [] as $q => $question) {
                        if ($question['id'] === $id) {
                            return ['type' => 'question', 'path' => [...$base, 'quiz', 'questions', $q], 'node' => $question, 'label' => "{$lLabel} › Q" . ($q + 1), 'lessonId' => $lesson['id']];
                        }
                    }
                }
            }
        }
        $final = $doc['finalTest'] ?? null;
        if (is_array($final)) {
            if ($final['id'] === $id) {
                return ['type' => 'quiz', 'path' => ['finalTest'], 'node' => $final, 'label' => 'Final test', 'lessonId' => null];
            }
            foreach ($final['questions'] ?? [] as $q => $question) {
                if ($question['id'] === $id) {
                    return ['type' => 'question', 'path' => ['finalTest', 'questions', $q], 'node' => $question, 'label' => 'Final test › Q' . ($q + 1), 'lessonId' => null];
                }
            }
        }

        return null;
    }

    /** @param array<int,string|int> $path */
    public static function setAt(array $doc, array $path, mixed $value): array
    {
        $ref = &$doc;
        foreach ($path as $key) {
            $ref = &$ref[$key];
        }
        $ref = $value;
        unset($ref);

        return $doc;
    }

    /** @return string[] every fragment id cited anywhere */
    public static function citations(array $doc): array
    {
        $ids = [];
        array_walk_recursive($doc, function ($value, $key) use (&$ids) {
            if (is_string($value) && str_starts_with($value, 'frg_')) {
                $ids[$value] = true;
            }
        });

        return array_keys($ids);
    }

    /** @return array{modules:int,lessons:int,minutes:int,objectives:int,questions:int,blocks:int,interactions:int} */
    public static function stats(array $doc): array
    {
        $stats = ['modules' => count($doc['modules'] ?? []), 'lessons' => 0, 'minutes' => 0, 'objectives' => count($doc['course']['objectives'] ?? []), 'questions' => 0, 'blocks' => 0, 'interactions' => 0];
        foreach (self::lessons($doc) as $item) {
            $lesson = $item['lesson'];
            $stats['lessons']++;
            $stats['minutes'] += (int) ($lesson['minutes'] ?? 0);
            $stats['objectives'] += count($lesson['objectives'] ?? []);
            $stats['blocks'] += count($lesson['blocks'] ?? []);
            $stats['questions'] += count($lesson['quiz']['questions'] ?? []);
            $stats['interactions'] += count($lesson['selfChecks'] ?? []) + (is_array($lesson['interaction'] ?? null) ? 1 : 0);
        }
        $stats['questions'] += count($doc['finalTest']['questions'] ?? []);

        return $stats;
    }

    /** Canonical JSON hash of an element (entity-map fingerprints). */
    public static function fingerprint(mixed $node): string
    {
        return hash('sha256', (string) json_encode(\Ulams\Ai\Fake\CassetteStore::canonical($node), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
