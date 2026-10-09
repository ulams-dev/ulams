<?php

namespace Ulams\LivingCourse\Diff;

use RuntimeException;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Models\RevisionFragment;

/**
 * Deterministic fragment-level diff of two revisions (ADR 0031), no model involved.
 *
 * Fragment ids are positional (heading path + ordinal), so inserting a paragraph can shift the
 * text of later chunks under unchanged ids. The alignment therefore trusts content before ids:
 * identical hash, then identical normalised hash, then same id with similar text, then fuzzy
 * moves by word-3-shingle similarity; whatever is left is removed or added.
 */
final class FragmentDiff
{
    /** Posting lists longer than this are ignored as too common to say anything (stop shingles). */
    private const MAX_POSTING = 60;

    /**
     * @param RevisionFragment[]|array<int,array<string,mixed>> $old
     * @param RevisionFragment[]|array<int,array<string,mixed>> $new
     * @throws RuntimeException when a side has more fragments than allowed
     */
    public function compare(iterable $old, iterable $new): FragmentChangeSet
    {
        $old = self::records($old);
        $new = self::records($new);
        $max = (int) config('living_course.diff.max_fragments', 4000);
        if (count($old) > $max || count($new) > $max) {
            throw new RuntimeException(sprintf('This source has more than %d fragments, which is more than a change check can compare. Split it into smaller sources.', $max));
        }

        $changes = [];
        $oldMatched = [];
        $newMatched = [];
        $oldIndex = array_flip(array_keys($old));
        $newIndex = array_flip(array_keys($new));
        $sameIdCandidates = [];

        // 1. same id: identical, cosmetic, or a candidate for a changed pair
        foreach ($old as $id => $o) {
            if (!isset($new[$id])) {
                continue;
            }
            $n = $new[$id];
            if ($o['content_hash'] === $n['content_hash']) {
                $oldMatched[$id] = $newMatched[$id] = true;
            } elseif ($o['normalised_hash'] === $n['normalised_hash']) {
                $oldMatched[$id] = $newMatched[$id] = true;
                $changes[] = self::change('changed', $id, $id, 'trivial', 1.0);
            } else {
                $sameIdCandidates[] = $id;
            }
        }

        // 2. moved without any change of text (same content under another id)
        foreach (['content_hash', 'normalised_hash'] as $hash) {
            $byHash = [];
            foreach ($new as $id => $n) {
                if (!isset($newMatched[$id])) {
                    $byHash[$n[$hash]][] = $id;
                }
            }
            foreach ($old as $id => $o) {
                if (isset($oldMatched[$id]) || empty($byHash[$o[$hash]])) {
                    continue;
                }
                $target = self::closest($o, $byHash[$o[$hash]], $new);
                $byHash[$o[$hash]] = array_values(array_diff($byHash[$o[$hash]], [$target]));
                $oldMatched[$id] = $newMatched[$target] = true;
                $changes[] = self::change('moved', $id, $target, 'trivial', 1.0);
            }
        }

        // 3. same id, different text: a changed pair when the texts are alike
        $shingles = [];
        $sh = function (array $r) use (&$shingles) {
            return $shingles[$r['id'] . $r['content_hash']] ??= self::shingles($r['text']);
        };
        $sameIdThreshold = (float) config('living_course.diff.same_id_similarity', 0.5);
        $perHeading = ['old' => [], 'new' => []];
        foreach (['old' => $old, 'new' => $new] as $side => $list) {
            foreach ($list as $r) {
                $key = ($r['file_path'] ?? '') . "\x1E" . implode("\x1E", (array) $r['heading_path']);
                $perHeading[$side][$key] = ($perHeading[$side][$key] ?? 0) + 1;
            }
        }
        $alone = fn (string $side, array $r) => ($perHeading[$side][($r['file_path'] ?? '') . "\x1E" . implode("\x1E", (array) $r['heading_path'])] ?? 0) === 1;
        foreach ($sameIdCandidates as $id) {
            if (isset($oldMatched[$id]) || isset($newMatched[$id])) {
                continue;
            }
            $s = self::jaccard($sh($old[$id]), $sh($new[$id]));
            if ($s < $sameIdThreshold) {
                // short fragments have few shingles, so one edit weighs a lot: the same heading position
                // with at least half of the tokens in common is still the same passage
                $diff = TokenDiff::diff($old[$id]['text'], $new[$id]['text']);
                $tokenSimilarity = 1 - max($diff['removed'], $diff['added']) / max(1, $diff['oldCount'], $diff['oldCount'] - $diff['removed'] + $diff['added']);
                if ($tokenSimilarity >= $sameIdThreshold) {
                    $s = max($s, $sameIdThreshold);
                }
            }
            // the only fragment under a heading in both revisions: a rewritten section, not a packing shift
            if ($s < $sameIdThreshold && $alone('old', $old[$id]) && $alone('new', $new[$id])) {
                $s = $sameIdThreshold;
            }
            if ($s >= $sameIdThreshold) {
                $oldMatched[$id] = $newMatched[$id] = true;
                $changes[] = self::withMagnitude('changed', $old[$id], $new[$id], min($s, 1.0));
            }
        }

        // 4. fuzzy moves among what is left, greedy by descending similarity
        $moveThreshold = (float) config('living_course.diff.move_similarity', 0.6);
        $unOld = array_filter($old, fn ($r) => !isset($oldMatched[$r['id']]));
        $unNew = array_filter($new, fn ($r) => !isset($newMatched[$r['id']]));
        if ($unOld !== [] && $unNew !== []) {
            $posting = [];
            foreach ($unNew as $id => $n) {
                foreach (array_keys($sh($n)) as $hash) {
                    $posting[$hash][] = $id;
                }
            }
            $pairs = [];
            foreach ($unOld as $oid => $o) {
                $sharing = [];
                foreach (array_keys($sh($o)) as $hash) {
                    if (!isset($posting[$hash]) || count($posting[$hash]) > self::MAX_POSTING) {
                        continue;
                    }
                    foreach ($posting[$hash] as $nid) {
                        $sharing[$nid] = true;
                    }
                }
                foreach (array_keys($sharing) as $nid) {
                    $s = self::jaccard($sh($o), $sh($unNew[$nid]));
                    if ($s >= $moveThreshold) {
                        $pairs[] = [$s, self::affinity($o, $unNew[$nid]), $oid, $nid];
                    }
                }
            }
            usort($pairs, fn ($x, $y) => [$y[0], $y[1], $oldIndex[$x[2]], $newIndex[$x[3]]] <=> [$x[0], $x[1], $oldIndex[$y[2]], $newIndex[$y[3]]]);
            foreach ($pairs as [$s, , $oid, $nid]) {
                if (isset($oldMatched[$oid]) || isset($newMatched[$nid])) {
                    continue;
                }
                $oldMatched[$oid] = $newMatched[$nid] = true;
                $changes[] = $old[$oid]['text'] === $new[$nid]['text'] || $old[$oid]['normalised_hash'] === $new[$nid]['normalised_hash']
                    ? self::change('moved', $oid, $nid, 'trivial', 1.0)
                    : self::withMagnitude('moved', $old[$oid], $new[$nid], $s);
            }
        }

        // 5. the rest
        foreach ($old as $id => $o) {
            if (!isset($oldMatched[$id])) {
                $changes[] = self::change('removed', $id, null, 'substantive', 0.0);
            }
        }
        foreach ($new as $id => $n) {
            if (!isset($newMatched[$id])) {
                $changes[] = self::change('added', null, $id, 'substantive', 0.0);
            }
        }

        return new FragmentChangeSet(self::ordered($changes, $oldIndex, $newIndex));
    }

    public function compareRevisions(Revision $from, Revision $to): FragmentChangeSet
    {
        return $this->compare(
            RevisionFragment::query()->where('revision_id', $from->id)->orderBy('ordinal')->get(),
            RevisionFragment::query()->where('revision_id', $to->id)->orderBy('ordinal')->get(),
        );
    }

    /** @return array<string,array<string,mixed>> id => record, in document order */
    private static function records(iterable $fragments): array
    {
        $out = [];
        foreach ($fragments as $f) {
            $r = $f instanceof RevisionFragment
                ? ['id' => $f->fragment_id, 'text' => $f->text, 'content_hash' => $f->content_hash, 'normalised_hash' => $f->normalised_hash, 'heading_path' => (array) $f->heading_path, 'file_path' => $f->file_path]
                : $f + ['heading_path' => [], 'file_path' => null];
            $out[$r['id']] = $r;
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private static function change(string $kind, ?string $old, ?string $new, string $magnitude, float $similarity, array $signals = [], ?array $wordDiff = null): array
    {
        return ['kind' => $kind, 'old' => $old, 'new' => $new, 'magnitude' => $magnitude, 'similarity' => round($similarity, 3), 'signals' => $signals, 'word_diff' => $wordDiff];
    }

    /** @return array<string,mixed> */
    private static function withMagnitude(string $kind, array $old, array $new, float $similarity): array
    {
        $diff = TokenDiff::diff($old['text'], $new['text']);
        $magnitude = $diff['signals'] !== [] ? 'substantive' : ($diff['removed'] + $diff['added'] === 0 ? 'trivial' : 'minor');

        return self::change($kind, $old['id'], $new['id'], $magnitude, $similarity, $diff['signals'], TokenDiff::cap($diff['ops'], (int) config('living_course.diff.word_diff_bytes', 8192)));
    }

    /** Prefers the candidate in the same file with the longest shared heading-path prefix. */
    private static function closest(array $old, array $candidates, array $new): string
    {
        $best = $candidates[0];
        $bestScore = -1;
        foreach ($candidates as $id) {
            $score = self::affinity($old, $new[$id]);
            if ($score > $bestScore) {
                $best = $id;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private static function affinity(array $a, array $b): int
    {
        $score = ($a['file_path'] ?? null) === ($b['file_path'] ?? null) ? 100 : 0;
        $pa = (array) $a['heading_path'];
        $pb = (array) $b['heading_path'];
        for ($i = 0; $i < min(count($pa), count($pb)) && $pa[$i] === $pb[$i]; $i++) {
            $score++;
        }

        return $score;
    }

    /** @return array<int,true> crc32 of the word 3-shingles of the normalised text */
    public static function shingles(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}_]+/u', mb_strtolower(Normaliser::text($text)), $m);
        $words = $m[0];
        $set = [];
        if (count($words) < 3) {
            if ($words !== []) {
                $set[crc32(implode(' ', $words))] = true;
            }

            return $set;
        }
        for ($i = 0, $n = count($words) - 2; $i < $n; $i++) {
            $set[crc32($words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2])] = true;
        }

        return $set;
    }

    /** @param array<int,true> $a @param array<int,true> $b */
    public static function jaccard(array $a, array $b): float
    {
        if ($a === [] && $b === []) {
            return 1.0;
        }
        $inter = count(array_intersect_key($a, $b));
        $union = count($a) + count($b) - $inter;

        return $union === 0 ? 0.0 : $inter / $union;
    }

    /**
     * Document order: by the new position where there is one; removed fragments sit right after
     * the nearest earlier old fragment that kept a position in the new revision.
     *
     * @param array<int,array<string,mixed>> $changes
     * @param array<string,int> $oldIndex
     * @param array<string,int> $newIndex
     * @return array<int,array<string,mixed>>
     */
    private static function ordered(array $changes, array $oldIndex, array $newIndex): array
    {
        $map = [];
        foreach ($oldIndex as $id => $oi) {
            if (isset($newIndex[$id])) {
                $map[$oi] = $newIndex[$id];
            }
        }
        foreach ($changes as $c) {
            if ($c['old'] !== null && $c['new'] !== null) {
                $map[$oldIndex[$c['old']]] = $newIndex[$c['new']];
            }
        }
        ksort($map);
        $keys = array_keys($map);
        $position = function (array $c) use ($map, $keys, $oldIndex, $newIndex): float {
            if ($c['new'] !== null) {
                return (float) $newIndex[$c['new']];
            }
            $oi = $oldIndex[$c['old']];
            $at = -0.5;
            // binary search for the last mapped old index before $oi
            $lo = 0;
            $hi = count($keys) - 1;
            while ($lo <= $hi) {
                $mid = intdiv($lo + $hi, 2);
                if ($keys[$mid] < $oi) {
                    $at = $map[$keys[$mid]] + 0.5;
                    $lo = $mid + 1;
                } else {
                    $hi = $mid - 1;
                }
            }

            return $at + $oi * 1e-6;
        };
        usort($changes, fn (array $x, array $y) => $position($x) <=> $position($y));

        return $changes;
    }
}
