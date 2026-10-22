<?php

namespace Ulams\CourseBuilder\Blueprint;

/**
 * Who is behind the current text of an element (ADR 0194, AM-2). The AI stamps what it produced with a hash of
 * the content (`ai_hash`); a person changing the element directly sets `edited_by_author`. Reading both later tells
 * apart an AI element accepted unchanged, one the AI revised on request, one an author edited, and one the author wrote.
 *
 * Elements are modules, lessons, lesson blocks and quiz questions (including the final test). A lesson's hash covers
 * the lesson itself (title, minutes, objectives, citations, format), not its blocks and quiz, which carry their own.
 */
final class Provenance
{
    public const ACCEPTED_UNCHANGED = 'accepted_unchanged';
    public const REVISED_ON_REQUEST = 'revised_on_request';
    public const EDITED_BY_AUTHOR = 'edited_by_author';
    public const AUTHOR_WRITTEN = 'author_written';
    public const UNKNOWN = 'unknown';

    /** Keys that are bookkeeping or child elements, not the content of the element they sit on. */
    private const NOT_CONTENT = ['id', 'key', 'origin', 'ai_hash', 'edited_by_author', 'meta', 'flags', 'status', 'modules', 'lessons', 'blocks', 'quiz', 'questions', 'finalTest', 'selfChecks', 'interaction'];

    /** sha256 of the normalised content: stable key order, no ids, trimmed text, Unix line ends. */
    public static function hash(array $element): string
    {
        $normalise = function (mixed $value, bool $top) use (&$normalise) {
            if (is_string($value)) {
                return trim(str_replace(["\r\n", "\r"], "\n", $value));
            }
            if (!is_array($value)) {
                return $value;
            }
            if (array_is_list($value)) {
                return array_map(fn ($v) => $normalise($v, false), $value);
            }
            $out = [];
            foreach ($value as $k => $v) {
                if ($k === 'id' || ($top && in_array($k, self::NOT_CONTENT, true)) || (!$top && in_array($k, ['meta'], true))) {
                    continue;
                }
                $out[$k] = $normalise($v, false);
            }
            ksort($out);

            return $out;
        };

        return hash('sha256', (string) json_encode($normalise($element, true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Sets `ai_hash` on one element the AI produced or revised, and clears `edited_by_author` (the AI owns the text again). */
    public static function stampElement(array $element): array
    {
        unset($element['edited_by_author']);
        $element['ai_hash'] = self::hash($element);

        return $element;
    }

    /**
     * Stamps every AI element of a document the AI just produced. Elements written by an author and elements an author
     * edited are left alone, so what the AI did not write never gets an AI hash.
     */
    public static function stamp(array $doc): array
    {
        $stampable = fn (array $e) => ($e['origin'] ?? 'ai') !== 'author' && empty($e['edited_by_author']);
        $one = fn (array $e) => $stampable($e) ? self::stampElement($e) : $e;
        $quiz = function (?array $q) use ($one) {
            if ($q === null || !isset($q['questions'])) {
                return $q;
            }
            $q['questions'] = array_map($one, $q['questions']);

            return $q;
        };
        foreach ($doc['modules'] ?? [] as $m => $module) {
            foreach ($module['lessons'] ?? [] as $l => $lesson) {
                if (isset($lesson['blocks'])) {
                    $lesson['blocks'] = array_map($one, $lesson['blocks']);
                }
                if (isset($lesson['quiz'])) {
                    $lesson['quiz'] = $quiz($lesson['quiz']);
                }
                $module['lessons'][$l] = $one($lesson);
            }
            $doc['modules'][$m] = $one($module);
        }
        if (isset($doc['finalTest'])) {
            $doc['finalTest'] = $quiz($doc['finalTest']);
        }

        return $doc;
    }

    /** The element as the model sees it: its hash and edit flag are the server's business and never leave in a prompt. */
    public static function withoutBookkeeping(array $element): array
    {
        unset($element['ai_hash'], $element['edited_by_author']);

        return $element;
    }

    /** Marks an element as edited by a person. */
    public static function markEdited(array $element): array
    {
        $element['edited_by_author'] = true;

        return $element;
    }

    /**
     * Applies `markEdited` to the elements with these ids, anywhere in the document.
     *
     * @param string[] $ids
     */
    public static function markEditedIds(array $doc, array $ids): array
    {
        $set = array_flip($ids);
        $mark = fn (array $e) => isset($e['id'], $set[$e['id']]) ? self::markEdited($e) : $e;
        $quiz = function (?array $q) use ($mark) {
            if ($q === null || !isset($q['questions'])) {
                return $q;
            }
            $q['questions'] = array_map($mark, $q['questions']);

            return $q;
        };
        foreach ($doc['modules'] ?? [] as $m => $module) {
            foreach ($module['lessons'] ?? [] as $l => $lesson) {
                if (isset($lesson['blocks'])) {
                    $lesson['blocks'] = array_map($mark, $lesson['blocks']);
                }
                if (isset($lesson['quiz'])) {
                    $lesson['quiz'] = $quiz($lesson['quiz']);
                }
                $module['lessons'][$l] = $mark($lesson);
            }
            $doc['modules'][$m] = $mark($module);
        }
        if (isset($doc['finalTest'])) {
            $doc['finalTest'] = $quiz($doc['finalTest']);
        }

        return $doc;
    }

    /**
     * What the element is today. `$patched` says an element chat patch produced its current text (the version
     * history records those: kind `patch` with the element id).
     */
    public static function classify(array $element, bool $patched = false): string
    {
        if (!empty($element['edited_by_author'])) {
            return self::EDITED_BY_AUTHOR;
        }
        if (($element['origin'] ?? 'ai') === 'author') {
            return self::AUTHOR_WRITTEN;
        }
        if (!isset($element['ai_hash'])) {
            return self::UNKNOWN; // produced before AM-2: counted as unknown, never guessed
        }
        if (!hash_equals($element['ai_hash'], self::hash($element))) {
            return self::EDITED_BY_AUTHOR;
        }

        return $patched ? self::REVISED_ON_REQUEST : self::ACCEPTED_UNCHANGED;
    }
}
