<?php

namespace Ulams\CourseBuilder\Blueprint;

/**
 * The semantic diff of two blueprint documents (ADR 0192, CV-2; plan docs/plans/course-versions-and-variants.md section 7).
 *
 * It wraps `BlueprintDiff::compare` (elements matched by id, changed fields with before and after) and adds, in one pass
 * over the flattened trees: moves and reorders, citation sets (added, removed, repointed), quiz semantics (answer key and
 * wording), a bounded payload for very long fields, and a summary with a human-readable text. Pure: no database, so the
 * same input always gives the same payload (`VersionComparer` adds source passages and caches it per pair).
 *
 * The payload shape is stable and versioned (`diffVersion`); studio, CLI and MCP tool read the same one.
 */
final class SemanticDiff
{
    public const VERSION = 1;

    /** Fields longer than this are not sent whole: the payload carries a line diff instead. */
    public const LONG_FIELD = 20000;

    /** The same rule Living Course uses for a minor edit (UpdateValidator::changeClass): at least 85 % alike. */
    public const MINOR_SIMILARITY = 0.85;

    private const LINE_DIFF_LIMIT = 200;

    /** Fields whose text decides whether a lesson change is minor or major. */
    private const TEXT_FIELDS = ['title', 'summary', 'text', 'markdown', 'stem', 'explanation'];

    private const KEY_CONSEQUENCE = 'Learners who already answered this question get a re-attempt notice and one extra attempt; their past score and attempt stay as they were (ADR 0033).';

    /**
     * @param array<string,string> $repointed old fragment id => the fragment it moved to in a newer source revision
     *                                        (from `living_course_fragment_changes`; empty when Living Course is not installed)
     *
     * @return array{diffVersion:int,summary:array<string,mixed>,changes:array<int,array<string,mixed>>,quiz:array<int,array<string,mixed>>}
     */
    public static function compare(array $from, array $to, array $repointed = []): array
    {
        $a = BlueprintDiff::flatten($from);
        $b = BlueprintDiff::flatten($to);
        $moves = self::moves($a, $b);
        $quiz = self::quiz($from, $to);

        $entries = [];
        $seen = [];
        foreach (BlueprintDiff::compare($from, $to) as $change) {
            $id = $change['id'];
            $seen[$id] = true;
            $entries[] = self::entry($change, $moves[$id] ?? null, $a[$id] ?? null, $b[$id] ?? null, $repointed);
        }
        foreach ($moves as $id => $move) {
            if (isset($seen[$id])) {
                continue;
            }
            $node = $b[$id];
            $entries[] = self::entry([
                'id' => $id, 'type' => $node['type'], 'label' => $node['label'], 'kind' => $move['moved'] !== null ? 'moved' : 'reordered',
                'ancestors' => $node['ancestors'], 'fields' => [],
            ], $move, $a[$id], $node, $repointed);
        }

        $summary = self::summary($from === [], $entries, $quiz, $a, $b);

        return ['diffVersion' => self::VERSION, 'summary' => $summary, 'changes' => $entries, 'quiz' => $quiz];
    }

    /** @return string[] every fragment id cited by an element that changed its citations (for the passage lookup) */
    public static function citedIds(array $payload): array
    {
        $ids = [];
        foreach ($payload['changes'] as $c) {
            foreach (['added', 'removed'] as $k) {
                foreach ($c['citations'][$k] ?? [] as $id) {
                    $ids[$id] = true;
                }
            }
            foreach ($c['citations']['repointed'] ?? [] as $pair) {
                $ids[$pair['from']] = true;
                $ids[$pair['to']] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Elements present in both documents whose parent differs (`moved`) or whose place among their siblings differs
     * although the parent is the same and they are outside the longest common subsequence of the sibling ids (`reordered`).
     *
     * @return array<string,array{moved:?array,reordered:?array}>
     */
    private static function moves(array $a, array $b): array
    {
        $parent = fn (array $node): string => $node['ancestors'] === [] ? '' : (string) end($node['ancestors']);
        $out = [];
        $stay = [];
        foreach ($b as $id => $node) {
            if (!isset($a[$id])) {
                continue;
            }
            if ($parent($a[$id]) !== $parent($node)) {
                $out[$id] = ['moved' => [
                    'from' => ['id' => $parent($a[$id]) ?: null, 'label' => $a[$parent($a[$id])]['label'] ?? null],
                    'to' => ['id' => $parent($node) ?: null, 'label' => $b[$parent($node)]['label'] ?? null],
                ], 'reordered' => null];
            } else {
                $stay[$id] = true;
            }
        }

        $children = function (array $nodes) use ($parent): array {
            $by = [];
            foreach ($nodes as $id => $node) {
                $by[$parent($node)][] = $id;
            }

            return $by;
        };
        $before = $children($a);
        $after = $children($b);
        foreach ($after as $key => $ids) {
            $seqB = array_values(array_filter($ids, fn ($id) => isset($stay[$id])));
            $seqA = array_values(array_filter($before[$key] ?? [], fn ($id) => isset($stay[$id])));
            if ($seqA === $seqB || count($seqA) > 1500) {
                continue;
            }
            $keep = array_flip(self::lcs($seqA, $seqB));
            foreach ($seqB as $id) {
                if (!isset($keep[$id])) {
                    $out[$id] = ['moved' => null, 'reordered' => [
                        'parentId' => $key ?: null,
                        'from' => (int) array_search($id, $before[$key], true) + 1,
                        'to' => (int) array_search($id, $ids, true) + 1,
                    ]];
                }
            }
        }

        return $out;
    }

    /** @return string[] the longest common subsequence of two id lists */
    private static function lcs(array $x, array $y): array
    {
        $n = count($x);
        $m = count($y);
        $t = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $t[$i][$j] = $x[$i] === $y[$j] ? $t[$i + 1][$j + 1] + 1 : max($t[$i + 1][$j], $t[$i][$j + 1]);
            }
        }
        $out = [];
        for ($i = 0, $j = 0; $i < $n && $j < $m;) {
            if ($x[$i] === $y[$j]) {
                $out[] = $x[$i];
                $i++;
                $j++;
            } elseif ($t[$i + 1][$j] >= $t[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return $out;
    }

    /** @param array<string,string> $repointed */
    private static function entry(array $change, ?array $move, ?array $was, ?array $now, array $repointed): array
    {
        $citations = null;
        if ($was !== null && $now !== null) {
            $citations = self::citations(self::ids($was['fields']['citations'] ?? ''), self::ids($now['fields']['citations'] ?? ''), $repointed);
        }
        $entry = $change + ['moved' => $move['moved'] ?? null, 'reordered' => $move['reordered'] ?? null, 'citations' => $citations];
        $entry['fields'] = array_map(self::bounded(...), $entry['fields']);
        $entry['description'] = self::describe($entry);

        return $entry;
    }

    /** @return string[] */
    private static function ids(mixed $field): array
    {
        // BlueprintDiff::flatten joins a citation list with ", " (fragment ids never contain a comma)
        return is_string($field) && $field !== '' ? array_values(array_filter(array_map('trim', explode(',', $field)))) : [];
    }

    /** @param array<string,string> $repointed @return array{added:string[],removed:string[],repointed:array<int,array{from:string,to:string}>}|null */
    private static function citations(array $was, array $now, array $repointed): ?array
    {
        $added = array_values(array_diff($now, $was));
        $removed = array_values(array_diff($was, $now));
        $pairs = [];
        foreach ($removed as $old) {
            $new = $repointed[$old] ?? null;
            if ($new !== null && in_array($new, $added, true)) {
                $pairs[] = ['from' => $old, 'to' => $new];
            }
        }
        $gone = array_column($pairs, 'from');
        $came = array_column($pairs, 'to');
        $added = array_values(array_diff($added, $came));
        $removed = array_values(array_diff($removed, $gone));

        return $added === [] && $removed === [] && $pairs === [] ? null : ['added' => $added, 'removed' => $removed, 'repointed' => $pairs];
    }

    /** @param array{field:string,label:string,before:mixed,after:mixed} $field */
    private static function bounded(array $field): array
    {
        $long = fn ($v) => is_string($v) && mb_strlen($v) > self::LONG_FIELD;
        if (!$long($field['before']) && !$long($field['after'])) {
            return $field;
        }

        return ['field' => $field['field'], 'label' => $field['label'], 'before' => null, 'after' => null, 'truncated' => true]
            + ['lineDiff' => self::lineDiff((string) $field['before'], (string) $field['after'])];
    }

    /** @return array{removed:string[],added:string[],omitted:int} */
    private static function lineDiff(string $before, string $after): array
    {
        $x = $before === '' ? [] : explode("\n", $before);
        $y = $after === '' ? [] : explode("\n", $after);
        if (count($x) <= 1500 && count($y) <= 1500) {
            $keep = self::lcs($x, $y);
            $removed = self::minus($x, $keep);
            $added = self::minus($y, $keep);
        } else {
            $removed = array_values(array_diff($x, $y));
            $added = array_values(array_diff($y, $x));
        }
        $omitted = max(0, count($removed) - self::LINE_DIFF_LIMIT) + max(0, count($added) - self::LINE_DIFF_LIMIT);
        $cut = fn (array $lines) => array_map(fn ($l) => mb_substr($l, 0, 500), array_slice($lines, 0, self::LINE_DIFF_LIMIT));

        return ['removed' => $cut($removed), 'added' => $cut($added), 'omitted' => $omitted];
    }

    /** @return string[] the items of $list that are not matched by $common, in order (both are sequences) */
    private static function minus(array $list, array $common): array
    {
        $out = [];
        $i = 0;
        foreach ($list as $item) {
            if ($i < count($common) && $common[$i] === $item) {
                $i++;
            } else {
                $out[] = $item;
            }
        }

        return $out;
    }

    private static function describe(array $e): string
    {
        $label = $e['label'];
        $parts = [];
        if ($e['kind'] === 'added') {
            return "{$label} added.";
        }
        if ($e['kind'] === 'removed') {
            return "{$label} removed.";
        }
        if ($e['moved'] !== null) {
            $parts[] = sprintf('moved from %s to %s', $e['moved']['from']['label'] ?? 'the top level', $e['moved']['to']['label'] ?? 'the top level');
        }
        if ($e['reordered'] !== null) {
            $parts[] = sprintf('moved from position %d to %d', $e['reordered']['from'], $e['reordered']['to']);
        }
        $fields = array_values(array_filter(array_map(fn ($f) => $f['field'] === 'citations' ? null : mb_strtolower($f['label']), $e['fields'])));
        if ($fields !== []) {
            $parts[] = 'changed ' . implode(', ', $fields);
        }
        $c = $e['citations'];
        if ($c !== null) {
            $bits = array_filter([
                $c['added'] ? count($c['added']) . ' added' : null,
                $c['removed'] ? count($c['removed']) . ' removed' : null,
                $c['repointed'] ? count($c['repointed']) . ' repointed to a newer source revision' : null,
            ]);
            $parts[] = 'citations ' . implode(', ', $bits);
        }

        return $label . ' ' . ($parts === [] ? 'changed' : implode('; ', $parts)) . '.';
    }

    /**
     * Quiz questions present in both documents with a change in type, stem, explanation or options. The answer key is the
     * set of correct option ids; a change of that set (a flag flipped, a correct option added or removed) is
     * `answerKeyChanged`. Text edits (stem, explanation, option text) are `wording`.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function quiz(array $from, array $to): array
    {
        $a = self::questions($from);
        $b = self::questions($to);
        $out = [];
        foreach ($b as $id => $now) {
            if (!isset($a[$id])) {
                continue;
            }
            $was = $a[$id]['node'];
            $q = $now['node'];
            $optsA = self::options($was);
            $optsB = self::options($q);
            $added = array_values(array_map(fn ($o) => ['id' => $o['id'], 'text' => $o['text'] ?? '', 'correct' => (bool) ($o['correct'] ?? false)], array_diff_key($optsB, $optsA)));
            $removed = array_values(array_map(fn ($o) => ['id' => $o['id'], 'text' => $o['text'] ?? '', 'correct' => (bool) ($o['correct'] ?? false)], array_diff_key($optsA, $optsB)));
            $reworded = [];
            $flipped = [];
            foreach (array_intersect_key($optsB, $optsA) as $oid => $o) {
                $p = $optsA[$oid];
                if (($p['text'] ?? '') !== ($o['text'] ?? '')) {
                    $reworded[] = ['id' => $oid, 'before' => $p['text'] ?? '', 'after' => $o['text'] ?? ''];
                }
                if ((bool) ($p['correct'] ?? false) !== (bool) ($o['correct'] ?? false)) {
                    $flipped[] = ['id' => $oid, 'text' => $o['text'] ?? '', 'before' => (bool) ($p['correct'] ?? false), 'after' => (bool) ($o['correct'] ?? false)];
                }
            }
            $keyA = self::key($optsA);
            $keyB = self::key($optsB);
            $keyChanged = $keyA !== $keyB;
            $stem = ($was['stem'] ?? '') !== ($q['stem'] ?? '') ? ['before' => $was['stem'] ?? '', 'after' => $q['stem'] ?? ''] : null;
            $type = ($was['type'] ?? null) !== ($q['type'] ?? null) ? ['before' => $was['type'] ?? null, 'after' => $q['type'] ?? null] : null;
            $explanation = ($was['explanation'] ?? '') !== ($q['explanation'] ?? '');
            $wording = $stem !== null || $explanation || $reworded !== [];
            if (!$keyChanged && !$wording && $type === null && $added === [] && $removed === []) {
                continue;
            }
            $out[] = [
                'questionId' => $id,
                'label' => $now['label'],
                'lessonId' => $now['lessonId'],
                'stem' => $stem,
                'type' => $type,
                'explanation' => $explanation,
                'options' => ['added' => $added, 'removed' => $removed, 'reworded' => $reworded, 'keyChanged' => $flipped],
                'answerKeyChanged' => $keyChanged,
                'wording' => $wording,
                'correctBefore' => self::correctTexts($optsA),
                'correctAfter' => self::correctTexts($optsB),
                'consequence' => $keyChanged ? self::KEY_CONSEQUENCE : null,
            ];
        }

        return $out;
    }

    /** @return array<string,array{node:array,label:string,lessonId:?string}> */
    private static function questions(array $doc): array
    {
        $out = [];
        $add = function (array $question, string $label, ?string $lessonId) use (&$out) {
            if (isset($question['id'])) {
                $out[$question['id']] = ['node' => $question, 'label' => $label, 'lessonId' => $lessonId];
            }
        };
        foreach ($doc['modules'] ?? [] as $m => $module) {
            foreach ($module['lessons'] ?? [] as $l => $lesson) {
                $lLabel = 'Lesson ' . ($m + 1) . '.' . ($l + 1);
                foreach ($lesson['selfChecks'] ?? [] as $c => $check) {
                    $add($check, "{$lLabel} › check " . ($c + 1), $lesson['id'] ?? null);
                }
                foreach ($lesson['quiz']['questions'] ?? [] as $q => $question) {
                    $add($question, "{$lLabel} › Q" . ($q + 1), $lesson['id'] ?? null);
                }
            }
        }
        foreach ($doc['finalTest']['questions'] ?? [] as $q => $question) {
            $add($question, 'Final test › Q' . ($q + 1), null);
        }

        return $out;
    }

    /** @return array<string,array<string,mixed>> option id => option */
    private static function options(array $question): array
    {
        $out = [];
        foreach ($question['options'] ?? [] as $option) {
            if (isset($option['id'])) {
                $out[$option['id']] = $option;
            }
        }

        return $out;
    }

    /** @return string[] sorted ids of the correct options */
    private static function key(array $options): array
    {
        $ids = array_keys(array_filter($options, fn ($o) => (bool) ($o['correct'] ?? false)));
        sort($ids);

        return $ids;
    }

    /** @return string[] */
    private static function correctTexts(array $options): array
    {
        return array_values(array_map(fn ($o) => (string) ($o['text'] ?? ''), array_filter($options, fn ($o) => (bool) ($o['correct'] ?? false))));
    }

    /**
     * @param array<int,array<string,mixed>> $entries
     * @param array<int,array<string,mixed>> $quiz
     *
     * @return array<string,mixed>
     */
    private static function summary(bool $first, array $entries, array $quiz, array $a, array $b): array
    {
        $blank = ['added' => 0, 'changed' => 0, 'removed' => 0, 'moved' => 0, 'reordered' => 0];
        $counts = $blank;
        $types = [];
        $citations = ['added' => 0, 'removed' => 0, 'repointed' => 0];
        foreach ($entries as $e) {
            $types[$e['type']] ??= $blank;
            foreach (['added', 'changed', 'removed'] as $kind) {
                if ($e['kind'] === $kind) {
                    $counts[$kind]++;
                    $types[$e['type']][$kind]++;
                }
            }
            foreach (['moved', 'reordered'] as $flag) {
                if ($e[$flag] !== null) {
                    $counts[$flag]++;
                    $types[$e['type']][$flag]++;
                }
            }
            foreach (['added', 'removed', 'repointed'] as $k) {
                $citations[$k] += count($e['citations'][$k] ?? []);
            }
        }
        ksort($types);

        $keys = count(array_filter($quiz, fn ($q) => $q['answerKeyChanged']));
        $reworded = count(array_filter($quiz, fn ($q) => $q['wording'] && !$q['answerKeyChanged']));
        $impact = self::learnerImpact($entries, $a, $b, $keys);
        $lessons = $types['lesson'] ?? $blank;
        $questions = $types['question'] ?? $blank;

        $summary = [
            'first' => $first,
            'counts' => $counts,
            'types' => $types,
            'citations' => $citations,
            'quiz' => ['answerKeysChanged' => $keys, 'reworded' => $reworded, 'questionsAdded' => $questions['added'], 'questionsRemoved' => $questions['removed']],
            'learnerImpact' => $impact,
        ];
        $summary['lines'] = self::lines($first, $lessons, $counts, $citations, $summary['quiz'], $impact);
        $summary['text'] = $summary['lines'] === [] ? 'No change.' : implode('; ', $summary['lines']) . '.';

        return $summary;
    }

    /**
     * What learners would notice if the `to` document went live over the `from` one, by the rules of ADR 0033: completion is
     * never revoked; a major change to a lesson (a removed element, or a text that is under 85 % alike) shows an "Updated"
     * notice, minor edits and citation updates are silent; a changed answer key gives a re-attempt notice.
     *
     * @return array{lessonsKeepingCompletion:int,lessonsWithUpdatedNotice:int,lessonsChangedSilently:int,lessonsRemoved:int,lessonsAdded:int,reattemptNotices:int}
     */
    private static function learnerImpact(array $entries, array $a, array $b, int $answerKeys): array
    {
        $lessonsA = array_filter($a, fn ($n) => $n['type'] === 'lesson');
        $both = array_filter($lessonsA, fn ($n, $id) => isset($b[$id]), ARRAY_FILTER_USE_BOTH);
        $major = [];
        $touched = [];
        foreach ($entries as $e) {
            $inside = array_values(array_filter([...$e['ancestors'], $e['id']], fn ($id) => isset($both[$id])));
            if ($inside === [] || ($e['kind'] === 'added')) {
                continue;
            }
            $lesson = $inside[0];
            if ($e['kind'] === 'moved' || $e['kind'] === 'reordered') {
                continue;
            }
            $touched[$lesson] = true;
            $isMajor = $e['kind'] === 'removed';
            foreach ($e['fields'] as $f) {
                if (!in_array($f['field'], self::TEXT_FIELDS, true)) {
                    continue;
                }
                if (($f['truncated'] ?? false) || self::similarity((string) $f['before'], (string) $f['after']) < self::MINOR_SIMILARITY) {
                    $isMajor = true;
                }
            }
            if ($isMajor) {
                $major[$lesson] = true;
            }
        }

        return [
            'lessonsKeepingCompletion' => count($both),
            'lessonsWithUpdatedNotice' => count($major),
            'lessonsChangedSilently' => count(array_diff_key($touched, $major)),
            'lessonsRemoved' => count(array_filter($lessonsA, fn ($n, $id) => !isset($b[$id]), ARRAY_FILTER_USE_BOTH)),
            'lessonsAdded' => count(array_filter($b, fn ($n, $id) => $n['type'] === 'lesson' && !isset($a[$id]), ARRAY_FILTER_USE_BOTH)),
            'reattemptNotices' => $answerKeys,
        ];
    }

    /** Share of the words of $before that survive in $after (1 = identical), like Living Course's minor-edit test. */
    public static function similarity(string $before, string $after): float
    {
        if ($before === $after) {
            return 1.0;
        }
        $words = fn (string $s) => array_count_values(preg_split('/\s+/u', mb_strtolower(trim($s)), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $x = $words($before);
        $y = $words($after);
        $removed = 0;
        $added = 0;
        foreach ($x as $w => $n) {
            $removed += max(0, $n - ($y[$w] ?? 0));
        }
        foreach ($y as $w => $n) {
            $added += max(0, $n - ($x[$w] ?? 0));
        }

        return 1 - max($removed, $added) / max(1, array_sum($x));
    }

    /** @return string[] */
    private static function lines(bool $first, array $lessons, array $counts, array $citations, array $quiz, array $impact): array
    {
        $n = fn (int $c, string $one, ?string $many = null) => $c . ' ' . ($c === 1 ? $one : ($many ?? $one . 's'));
        $lines = [];
        if ($first) {
            return $lessons['added'] ? [$n($lessons['added'], 'lesson') . ' added (the first release)'] : [];
        }
        foreach (['added' => 'added', 'removed' => 'removed', 'changed' => 'changed', 'moved' => 'moved', 'reordered' => 'reordered'] as $k => $word) {
            if ($lessons[$k]) {
                $lines[] = $n($lessons[$k], 'lesson') . " {$word}";
            }
        }
        $others = $counts['added'] + $counts['removed'] + $counts['changed'] - $lessons['added'] - $lessons['removed'] - $lessons['changed'];
        if ($others > 0) {
            $lines[] = $n($others, 'other element') . ' added, removed or changed';
        }
        $moves = $counts['moved'] + $counts['reordered'] - $lessons['moved'] - $lessons['reordered'];
        if ($moves > 0) {
            $lines[] = $n($moves, 'other element') . ' moved or reordered';
        }
        foreach ($citations as $k => $c) {
            if ($c) {
                $lines[] = $n($c, 'citation') . " {$k}";
            }
        }
        if ($quiz['answerKeysChanged']) {
            $lines[] = $n($quiz['answerKeysChanged'], 'answer key') . ' changed';
        }
        if ($quiz['reworded']) {
            $lines[] = $n($quiz['reworded'], 'question') . ' reworded';
        }
        if ($impact['lessonsWithUpdatedNotice']) {
            $lines[] = $n($impact['lessonsWithUpdatedNotice'], 'lesson') . " would show an \"Updated\" notice to learners";
        }

        return $lines;
    }
}
