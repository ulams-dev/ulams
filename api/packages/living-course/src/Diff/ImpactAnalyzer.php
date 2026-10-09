<?php

namespace Ulams\LivingCourse\Diff;

/**
 * Deterministic impact analysis (ADR 0031, plan section 7): which course elements cite a fragment
 * that changed, and which quiz questions may now have a wrong answer. No model involved; the
 * citations of the Course Blueprint are the index.
 *
 * Elements are the content-bearing parts of the blueprint: course and lesson objectives, lesson
 * blocks, quiz questions (options inherit their question) and the lesson itself and the course FAQ
 * for citation housekeeping.
 */
final class ImpactAnalyzer
{
    /**
     * @param array<string,mixed> $doc the session's current approved content version
     * @param array<int,array<string,mixed>> $changes fragment changes: id, kind, old, new, magnitude, signals (as FragmentChangeSet or stored rows)
     * @param array<string,string> $labels fragment id (old and new) => section label, for reasons
     * @return array{items:array<int,array<string,mixed>>,uncovered:array<int,array<string,mixed>>,groups:array<int,array<string,mixed>>,remap:array<string,string>}
     */
    public function analyse(array $doc, array $changes, array $labels = []): array
    {
        $elements = $this->elements($doc);
        $index = [];
        foreach ($elements as $id => $el) {
            foreach ($el['citations'] as $fragment) {
                $index[$fragment][$id] = true;
            }
        }

        $impacted = [];
        $remapped = [];
        $remap = [];
        $uncovered = [];
        foreach ($changes as $c) {
            $changeId = $c['id'] ?? null;
            if ($c['kind'] === 'changed' && $c['magnitude'] === 'trivial') {
                continue;
            }
            if ($c['kind'] === 'added') {
                $uncovered[] = ['fragment' => $c['new'], 'label' => $labels[$c['new']] ?? $c['new'], 'changeId' => $changeId];
                continue;
            }
            $citing = array_keys($index[$c['old']] ?? []);
            if ($c['kind'] === 'moved') {
                $remap[$c['old']] = $c['new'];
            }
            foreach ($citing as $elementId) {
                if ($c['kind'] === 'moved' && $c['magnitude'] === 'trivial') {
                    $remapped[$elementId]['moves'][$c['old']] = $c['new'];
                    $remapped[$elementId]['changeIds'][] = $changeId;
                    continue;
                }
                $impacted[$elementId]['changes'][] = $c;
            }
        }

        $items = [];
        foreach ($impacted as $elementId => $info) {
            $el = $elements[$elementId];
            // citation housekeeping elements (lesson, FAQ) only need attention when a cited section disappeared
            if ($el['remapOnly'] && array_filter($info['changes'], fn ($c) => $c['kind'] === 'removed') === []) {
                continue;
            }
            $fragments = array_values(array_unique(array_map(fn ($c) => $c['old'], $info['changes'])));
            $substantive = array_filter($info['changes'], fn ($c) => $c['kind'] === 'removed' || $c['magnitude'] === 'substantive');
            $allRemoved = array_filter($info['changes'], fn ($c) => $c['kind'] === 'removed');
            $items[] = [
                'element_id' => $elementId,
                'element_type' => $el['type'],
                'group_key' => $el['group'],
                'label' => $el['label'],
                'kind' => $el['remapOnly'] ? 'citation_remap' : 'manual',
                'severity' => $substantive !== [] ? 'major' : 'minor',
                'answer_check' => $el['type'] === 'question' && $substantive !== [],
                'fragment_ids' => $fragments,
                'change_ids' => array_values(array_filter(array_map(fn ($c) => $c['id'] ?? null, $info['changes']))),
                'removed' => count($allRemoved) === count($info['changes']),
                'reason' => $el['remapOnly'] ? 'A cited source section was removed; its citation is dropped.' : self::reason($info['changes'], $labels, $el),
                'signals' => array_values(array_unique(array_merge(...array_map(fn ($c) => $c['signals'] ?? [], $info['changes']) ?: [[]]))),
                'moves' => array_merge($remapped[$elementId]['moves'] ?? [], array_filter($remap, fn ($new, $old) => in_array($old, $fragments, true), ARRAY_FILTER_USE_BOTH)),
            ];
        }
        foreach ($remapped as $elementId => $info) {
            if (isset($impacted[$elementId])) {
                continue;
            }
            $el = $elements[$elementId];
            $items[] = [
                'element_id' => $elementId,
                'element_type' => $el['type'],
                'group_key' => $el['group'],
                'label' => $el['label'],
                'kind' => 'citation_remap',
                'severity' => 'minor',
                'answer_check' => false,
                'fragment_ids' => array_keys($info['moves']),
                'change_ids' => array_values(array_filter($info['changeIds'])),
                'removed' => false,
                'reason' => 'The cited source section moved; only the citation changes, the text stays the same.',
                'signals' => [],
                'moves' => $info['moves'],
            ];
        }

        return [
            'items' => $items,
            'uncovered' => $uncovered,
            'groups' => self::groups($items),
            'remap' => $remap,
        ];
    }

    /**
     * @param array<string,mixed> $doc
     * @return array<string,array{type:string,label:string,group:string,citations:string[],remapOnly:bool,node:array}>
     */
    public function elements(array $doc): array
    {
        $out = [];
        $add = function (string $id, string $type, string $label, string $group, array $citations, array $node, bool $remapOnly = false) use (&$out) {
            $out[$id] = ['type' => $type, 'label' => $label, 'group' => $group, 'citations' => array_values(array_unique($citations)), 'remapOnly' => $remapOnly, 'node' => $node];
        };
        foreach ($doc['course']['objectives'] ?? [] as $n => $o) {
            $add($o['id'], 'objective', 'Course objective ' . ($n + 1), 'course', $o['citations'] ?? [], $o);
        }
        if (($doc['course']['faq'] ?? []) !== []) {
            $faq = [];
            foreach ($doc['course']['faq'] as $f) {
                $faq = [...$faq, ...($f['citations'] ?? [])];
            }
            $add($doc['course']['id'], 'course', 'Course FAQ', 'course', $faq, $doc['course'], true);
        }
        foreach ($doc['modules'] ?? [] as $m => $module) {
            foreach ($module['lessons'] ?? [] as $l => $lesson) {
                $number = ($m + 1) . '.' . ($l + 1);
                $group = 'lesson:' . $lesson['id'];
                $add($lesson['id'], 'lesson', "Lesson {$number}", $group, $lesson['citations'] ?? [], $lesson, true);
                foreach ($lesson['objectives'] ?? [] as $n => $o) {
                    $add($o['id'], 'objective', "Lesson {$number} › objective " . ($n + 1), $group, $o['citations'] ?? [], $o);
                }
                foreach ($lesson['blocks'] ?? [] as $b => $block) {
                    $add($block['id'], 'block', "Lesson {$number} › block " . ($b + 1), $group, $block['citations'] ?? [], $block);
                }
                foreach ($lesson['quiz']['questions'] ?? [] as $q => $question) {
                    $add($question['id'], 'question', "Lesson {$number} › Q" . ($q + 1), $group, $question['citations'] ?? [], $question);
                }
            }
        }
        foreach ($doc['finalTest']['questions'] ?? [] as $q => $question) {
            $add($question['id'], 'question', 'Final test › Q' . ($q + 1), 'final_test', $question['citations'] ?? [], $question);
        }

        return $out;
    }

    /** @param array<int,array<string,mixed>> $changes */
    private static function reason(array $changes, array $labels, array $el): string
    {
        $parts = [];
        foreach (array_slice($changes, 0, 3) as $c) {
            $label = $labels[$c['old']] ?? $c['old'];
            $parts[] = match (true) {
                $c['kind'] === 'removed' => "{$label} was removed from the source",
                $c['kind'] === 'moved' => "{$label} moved and its text changed",
                $c['magnitude'] === 'substantive' => "{$label} changed" . (($c['signals'] ?? []) !== [] ? ' (' . implode(', ', array_map(self::signalText(...), $c['signals'])) . ')' : ''),
                default => "{$label} changed slightly",
            };
        }
        $more = count($changes) > 3 ? ' and ' . (count($changes) - 3) . ' more' : '';

        return mb_substr(implode('; ', $parts) . $more . '.', 0, 480);
    }

    public static function signalText(string $signal): string
    {
        return match ($signal) {
            'number' => 'a number changed',
            'code' => 'code changed',
            'identifier' => 'a name or option changed',
            'modality' => 'a "must", "not" or similar word changed',
            'large' => 'much of the text changed',
            default => $signal,
        };
    }

    /**
     * Update groups: one per lesson (its blocks, objectives and questions), one for the course
     * objectives, one for the final test; ordered by the number of substantive items.
     *
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array{key:string,items:int,major:int}>
     */
    private static function groups(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            if ($item['kind'] === 'citation_remap') {
                continue;
            }
            $g = &$groups[$item['group_key']];
            $g ??= ['key' => $item['group_key'], 'items' => 0, 'major' => 0];
            $g['items']++;
            $g['major'] += $item['severity'] === 'major' ? 1 : 0;
            unset($g);
        }
        $list = array_values($groups);
        usort($list, fn ($a, $b) => [$b['major'], $b['items']] <=> [$a['major'], $a['items']]);

        return $list;
    }
}
