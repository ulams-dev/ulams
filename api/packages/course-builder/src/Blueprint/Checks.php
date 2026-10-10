<?php

namespace Ulams\CourseBuilder\Blueprint;

/**
 * Deterministic checks shared by the output validators and the blueprint validator: citations
 * resolve to fragments of the session, no markup outside code, quiz answers are supported by the
 * cited text, question shapes are consistent.
 */
final class Checks
{
    private const STOP = ['the', 'and', 'for', 'with', 'that', 'this', 'from', 'your', 'are', 'was', 'what', 'which', 'when', 'how', 'into', 'than', 'then', 'they', 'them', 'have', 'has', 'not', 'but', 'you', 'can', 'will', 'should', 'would', 'about', 'more', 'most', 'each', 'true', 'false', 'does', 'is', 'of', 'to', 'in', 'a', 'an', 'on', 'or', 'it', 'be', 'as', 'at', 'by'];

    /**
     * Raw HTML outside code (fenced or inline), `javascript:` links and remote images are rejected.
     *
     * @return string[]
     */
    public static function markup(string $text, string $where): array
    {
        $stripped = (string) preg_replace('/```.*?```|~~~.*?~~~/s', '', $text);
        $stripped = (string) preg_replace('/`[^`\n]*`/', '', $stripped);
        $errors = [];
        if (preg_match('/<\s*\/?\s*[a-zA-Z][a-zA-Z0-9-]*(\s[^<>]*)?\/?>/', $stripped)) {
            $errors[] = "{$where}: contains HTML; use plain text or Markdown";
        }
        if (preg_match('/\]\(\s*(javascript|data|vbscript):/i', $stripped) || preg_match('/\bjavascript:/i', $stripped)) {
            $errors[] = "{$where}: contains a script link";
        }
        if (preg_match('/!\[[^\]]*\]\(\s*https?:/i', $stripped)) {
            $errors[] = "{$where}: images from outside the course are not allowed";
        }

        return $errors;
    }

    /**
     * @param string[] $ids
     * @param array<string,bool> $known fragment id → true
     * @return string[]
     */
    public static function citations(array $ids, array $known, string $where, int $min = 1): array
    {
        $errors = [];
        if (count($ids) < $min) {
            $errors[] = "{$where}: must cite at least {$min} source fragment";
        }
        foreach ($ids as $id) {
            if (!isset($known[$id])) {
                $errors[] = "{$where}: cites {$id}, which is not a fragment of the source";
            }
        }

        return $errors;
    }

    /**
     * Shape rules per question type.
     *
     * @param array{type:string,options:array<int,array{text:string,correct:bool}>} $q
     * @return string[]
     */
    public static function questionShape(array $q, string $where): array
    {
        $options = $q['options'] ?? [];
        $correct = count(array_filter($options, fn ($o) => !empty($o['correct'])));
        $texts = array_map(fn ($o) => mb_strtolower(trim((string) ($o['text'] ?? ''))), $options);

        return match ($q['type'] ?? '') {
            'single' => array_values(array_filter([
                count($options) < 3 ? "{$where}: a single-choice question needs at least 3 options" : null,
                $correct !== 1 ? "{$where}: a single-choice question needs exactly one correct option" : null,
                count(array_unique($texts)) !== count($texts) ? "{$where}: options repeat" : null,
            ])),
            'multiple' => array_values(array_filter([
                count($options) < 3 ? "{$where}: a multiple-choice question needs at least 3 options" : null,
                $correct < 2 ? "{$where}: a multiple-answer question needs at least 2 correct options" : null,
                $correct === count($options) ? "{$where}: at least one option must be wrong" : null,
            ])),
            'truefalse' => array_values(array_filter([
                count($options) !== 2 || array_diff($texts, ['true', 'false']) !== [] ? "{$where}: true/false options must be exactly True and False" : null,
                $correct !== 1 ? "{$where}: exactly one of True/False is correct" : null,
            ])),
            'short' => array_values(array_filter([
                $correct !== count($options) || $options === [] ? "{$where}: every short-answer option is an accepted answer (correct: true)" : null,
            ])),
            default => ["{$where}: unknown question type"],
        };
    }

    /**
     * Quiz answers must be supported by the cited text: the stem and correct answers share at
     * least `$min` content words with the cited fragments (numbers count as words).
     *
     * @param string[] $citedTexts
     * @return string[]
     */
    public static function quizSupport(array $q, array $citedTexts, string $where, int $min = 2): array
    {
        if ($citedTexts === []) {
            return [];
        }
        $source = self::words(implode(' ', $citedTexts));
        $claim = $q['stem'] ?? '';
        foreach ($q['options'] ?? [] as $o) {
            if (!empty($o['correct'])) {
                $claim .= ' ' . ($o['text'] ?? '');
            }
        }
        $shared = array_intersect_key(self::words($claim), $source);

        return count($shared) >= $min ? [] : ["{$where}: the question and its answer share too little with the cited fragments; cite the fragment that states the answer"];
    }

    /** @return array<string,bool> */
    public static function words(string $text): array
    {
        $out = [];
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $m);
        foreach ($m[0] as $w) {
            if ((mb_strlen($w) >= 4 || is_numeric($w)) && !in_array($w, self::STOP, true)) {
                $out[$w] = true;
                // naive stemming: plural and -ing forms
                $out[preg_replace('/(ing|es|s)$/u', '', $w)] = true;
            }
        }

        return $out;
    }

    /**
     * Whole-document check run before an apply.
     *
     * @param array<string,bool> $known
     * @return string[] errors and warnings prefixed with "warning:"
     */
    public static function blueprint(array $doc, array $known, ?\Ulams\CourseBuilder\ContentTypes\ContentTypeRegistry $types = null): array
    {
        $types ??= app(\Ulams\CourseBuilder\ContentTypes\ContentTypeRegistry::class);
        $errors = [];
        foreach (Blueprint::lessons($doc) as $item) {
            $lesson = $item['lesson'];
            $where = 'Lesson ' . $item['number'];
            if (($lesson['objectives'] ?? []) === []) {
                $errors[] = "{$where}: serves no approved objective";
            }
            $objectiveIds = array_column($lesson['objectives'] ?? [], 'id');
            foreach ($lesson['blocks'] ?? [] as $b => $block) {
                $w = "{$where} block " . ($b + 1);
                $errors = [...$errors, ...self::citations($block['citations'] ?? [], $known, $w), ...self::markup((string) $block['markdown'], $w)];
                foreach ($block['objectiveIds'] ?? [] as $oid) {
                    if (!in_array($oid, $objectiveIds, true)) {
                        $errors[] = "{$w}: refers to an objective of another lesson";
                    }
                }
            }
            foreach ($lesson['quiz']['questions'] ?? [] as $q => $question) {
                $w = "{$where} Q" . ($q + 1);
                $errors = [...$errors, ...self::citations($question['citations'] ?? [], $known, $w), ...self::questionShape($question, $w)];
            }
            $errors = [...$errors, ...$types->forLesson($lesson)->check($lesson, $where, $known)];
            foreach ($lesson['flags'] ?? [] as $flag) {
                $errors[] = "warning: {$where}: {$flag}";
            }
            if (($lesson['status'] ?? '') !== 'generated') {
                $errors[] = "{$where}: has no generated content";
            }
        }
        foreach ($doc['finalTest']['questions'] ?? [] as $q => $question) {
            $w = 'Final test Q' . ($q + 1);
            $errors = [...$errors, ...self::citations($question['citations'] ?? [], $known, $w), ...self::questionShape($question, $w)];
        }

        return $errors;
    }
}
