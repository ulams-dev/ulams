<?php

namespace Ulams\CourseBuilder\CourseAsCode;

/**
 * The element-level three-way merge of course-as-code (ADR 0160, plan 7.4 and 7.5). A Blueprint v3
 * document is a tree of elements matched by id; `merge($base, $ours, $theirs)` decides per element,
 * never per field: one conflict per element (default 4 of the plan). The CLI runs the same
 * algorithm (front/cli/src/course/merge.ts); tests/fixtures/merge/*.json is run by both test
 * suites so the two cannot drift.
 *
 * "ours" is the side that pushes (the author's folder), "theirs" is what the server holds now.
 * Elements in conflict keep their "ours" form in the merged document (or stay out of it when ours
 * removed them), so a caller that refuses on conflicts never stores a half-merged document.
 *
 * A node is `['id','type','parent','slot','own','norm','keys']`: own data without children and
 * `meta`, the canonical text of that data after normalisation, and which child slots the document
 * wrote as keys (an empty list and a null interaction are kept as they were).
 */
final class ElementMerge
{
    public const DOC = '$doc';

    /** parent type => slot => [child type, holds a list] */
    private const SLOTS = [
        'doc' => ['course' => ['course', false], 'modules' => ['module', true], 'finalTest' => ['quiz', false], 'pages' => ['pages', false]],
        'course' => ['objectives' => ['objective', true]],
        'module' => ['lessons' => ['lesson', true]],
        'lesson' => ['objectives' => ['objective', true], 'blocks' => ['block', true], 'selfChecks' => ['question', true], 'interaction' => ['interaction', false], 'quiz' => ['quiz', false]],
        'quiz' => ['questions' => ['question', true]],
        'question' => ['options' => ['option', true]],
    ];

    private const ROOT_ELEMENT_KEYS = ['course', 'modules', 'finalTest', 'pages', 'sources', 'meta'];
    private const DEFAULT_LISTS = ['citations', 'objectiveIds', 'flags'];

    /** @var array{nodes:array<string,array>,kids:array<string,string[]>} */
    private array $B;
    /** @var array{nodes:array<string,array>,kids:array<string,string[]>} */
    private array $L;
    /** @var array{nodes:array<string,array>,kids:array<string,string[]>} */
    private array $R;

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $ours
     * @param array<string,mixed> $theirs
     * @return array{doc:array<string,mixed>,conflicts:array<int,array<string,mixed>>,notes:array<int,array<string,string>>,fromOurs:array<int,array{id:string,kind:string}>,fromTheirs:array<int,array{id:string,kind:string}>}
     */
    public static function merge(array $base, array $ours, array $theirs): array
    {
        $m = new self();
        $m->B = self::tree($base);
        $m->L = self::tree($ours);
        $m->R = self::tree($theirs);

        return $m->run();
    }

    /** @return array{nodes:array<string,array>,kids:array<string,string[]>} */
    public static function tree(array $doc): array
    {
        $t = ['nodes' => [], 'kids' => []];
        $walk = function (array $node, string $id, string $type, string $parent, string $slot) use (&$walk, &$t): void {
            $slots = self::SLOTS[$type] ?? [];
            self::addNode($t, $node, $id, $type, $parent, $slot, $slots);
            foreach ($slots as $name => [$childType, $many]) {
                $v = $node[$name] ?? null;
                $items = $many ? (is_array($v) && array_is_list($v) ? $v : []) : (is_array($v) && !array_is_list($v) ? [$v] : []);
                foreach ($items as $item) {
                    if (is_array($item) && isset($item['id']) && is_string($item['id'])) {
                        $walk($item, $item['id'], $childType, $id, $name);
                    }
                }
            }
        };
        $root = array_diff_key($doc, array_flip(self::ROOT_ELEMENT_KEYS));
        $t['nodes'][self::DOC] = ['id' => self::DOC, 'type' => 'doc', 'parent' => '', 'slot' => '', 'own' => $root, 'norm' => self::canon(self::normalise($root)), 'keys' => array_key_exists('finalTest', $doc) && $doc['finalTest'] === null ? ['finalTest' => 'null'] : []];
        foreach (self::SLOTS['doc'] as $name => [$childType, $many]) {
            $v = $doc[$name] ?? null;
            if ($name === 'pages') {
                $n = is_array($v) ? self::normalise($v) : null;
                if (is_array($v) && is_array($n) && $n !== []) {
                    $t['nodes']['pages'] = ['id' => 'pages', 'type' => 'pages', 'parent' => self::DOC, 'slot' => 'pages', 'own' => $v, 'norm' => self::canon($n), 'keys' => []];
                    $t['kids'][self::kidsKey(self::DOC, 'pages')] = ['pages'];
                } elseif (is_array($v)) {
                    $t['nodes'][self::DOC]['own']['pages'] = $v; // empty slots: kept as they were, not compared
                }
                continue;
            }
            $items = $many ? (is_array($v) && array_is_list($v) ? $v : []) : (is_array($v) && !array_is_list($v) ? [$v] : []);
            foreach ($items as $item) {
                if (is_array($item) && isset($item['id']) && is_string($item['id'])) {
                    $walk($item, $item['id'], $childType, self::DOC, $name);
                }
            }
        }

        return $t;
    }

    /** The document a tree describes. */
    public static function build(array $t): array
    {
        $make = function (string $id) use (&$make, $t): array {
            $n = $t['nodes'][$id];
            $out = $n['own'];
            foreach (self::SLOTS[$n['type']] ?? [] as $name => [$childType, $many]) {
                $list = $t['kids'][self::kidsKey($id, $name)] ?? [];
                if ($many) {
                    if ($list !== [] || ($n['keys'][$name] ?? null) === 'list') {
                        $out[$name] = array_map($make, $list);
                    }
                } elseif ($list !== []) {
                    $out[$name] = $make($list[0]);
                } elseif (($n['keys'][$name] ?? null) === 'null') {
                    $out[$name] = null;
                }
            }

            return $out;
        };
        $doc = $t['nodes'][self::DOC]['own'];
        foreach (self::SLOTS['doc'] as $name => [$childType, $many]) {
            $list = $t['kids'][self::kidsKey(self::DOC, $name)] ?? [];
            if ($name === 'pages') {
                if ($list !== []) {
                    $doc['pages'] = $t['nodes']['pages']['own'];
                }
            } elseif ($many) {
                $doc[$name] = array_map($make, $list);
            } elseif ($list !== []) {
                $doc[$name] = $make($list[0]);
            } elseif (($t['nodes'][self::DOC]['keys'][$name] ?? null) === 'null') {
                $doc[$name] = null;
            }
        }

        return $doc;
    }

    /** @param array<string,array{0:string,1:bool}> $slots */
    private static function addNode(array &$t, array $node, string $id, string $type, string $parent, string $slot, array $slots): void
    {
        $own = [];
        $keys = [];
        foreach ($node as $k => $v) {
            if ($k === 'meta') {
                continue;
            }
            if (isset($slots[$k])) {
                $keys[$k] = is_array($v) && array_is_list($v) ? 'list' : ($v === null ? 'null' : 'one');
                continue;
            }
            $own[$k] = $v;
        }
        $t['nodes'][$id] = ['id' => $id, 'type' => $type, 'parent' => $parent, 'slot' => $slot, 'own' => $own, 'norm' => self::canon(self::normalise($own)), 'keys' => $keys];
        if ($parent !== '') {
            $t['kids'][self::kidsKey($parent, $slot)][] = $id;
        }
    }

    private static function kidsKey(string $parent, string $slot): string
    {
        return $parent . "\0" . $slot;
    }

    /** What the folder format cannot tell apart: null and absent, an empty list and absent, the defaults it leaves out, Markdown line ends. */
    private static function normalise(mixed $value, ?string $key = null): mixed
    {
        if (!is_array($value)) {
            return is_string($value) && $key === 'markdown' ? self::markdown($value) : $value;
        }
        if (array_is_list($value)) {
            return array_map(fn ($v) => self::normalise($v), $value);
        }
        $out = [];
        foreach ($value as $k => $v) {
            if ($k === 'meta' || $v === null) {
                continue;
            }
            if ($k === 'data') {
                $out[$k] = $v;
                continue;
            }
            if (in_array($k, self::DEFAULT_LISTS, true) && $v === []) {
                continue;
            }
            if (($k === 'contentType' && $v === 'richtext') || ($k === 'status' && $v === 'generated') || ($k === 'correct' && $v === false)) {
                continue;
            }
            $out[$k] = self::normalise($v, (string) $k);
        }

        return $out;
    }

    private static function markdown(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", ltrim($text, "\u{FEFF}"));
        $text = (string) preg_replace('/^(?:[ \t]*\n)+/', '', $text);

        return rtrim($text);
    }

    private static function canon(mixed $value): string
    {
        $sort = function (mixed $v) use (&$sort): mixed {
            if (!is_array($v)) {
                return $v;
            }
            if (array_is_list($v)) {
                return array_map($sort, $v);
            }
            ksort($v);

            return array_map($sort, $v);
        };

        return (string) json_encode($sort($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    /* ------------------------------------------------------------ the merge */

    private function run(): array
    {
        $B = $this->B;
        $L = $this->L;
        $R = $this->R;
        $ids = array_values(array_unique([...array_keys($B['nodes']), ...array_keys($L['nodes']), ...array_keys($R['nodes'])]));
        $conflicts = [];
        $fromOurs = [];
        $fromTheirs = [];
        $raw = fn (?array $n) => $n === null ? null : $n['own'];
        $edited = fn (?array $s, ?array $b) => $s !== null && $b !== null && $s['norm'] !== $b['norm'];
        $moved = fn (?array $s, ?array $b) => $s !== null && $b !== null && !self::placed($s, $b);

        /* 1. an element one side removed while the other side still holds it */
        $held = [];
        $removals = [];
        foreach (array_keys($B['nodes']) as $id) {
            if ($id === self::DOC) {
                continue;
            }
            foreach ([['removed-ours', $L, $R], ['removed-theirs', $R, $L]] as [$kind, $remover, $keeper]) {
                if (isset($remover['nodes'][$id]) || !isset($keeper['nodes'][$id])) {
                    continue;
                }
                $subtree = $this->subtree($id, $keeper);
                foreach ($subtree as $f) {
                    $k = $keeper['nodes'][$f] ?? null;
                    $b = $B['nodes'][$f] ?? null;
                    if ($k !== null && ($b === null || $edited($k, $b) || $moved($k, $b))) {
                        $removals[] = ['id' => $id, 'kind' => $kind];
                        break;
                    }
                }
            }
        }
        $removalIds = array_flip(array_column($removals, 'id'));
        foreach ($removals as $c) {
            $inner = false;
            foreach (array_keys($B['nodes']) as $a) {
                if ($a !== $c['id'] && isset($removalIds[$a]) && self::below($B, $c['id'], $a)) {
                    $inner = true;
                    break;
                }
            }
            if ($inner) {
                continue;
            }
            $keeper = $c['kind'] === 'removed-ours' ? $R : $L;
            $subtree = $this->subtree($c['id'], $keeper);
            foreach ($subtree as $f) {
                $held[$f] = $c['id'];
            }
            $b = $raw($B['nodes'][$c['id']] ?? null);
            $l = $raw($L['nodes'][$c['id']] ?? null);
            $r = $raw($R['nodes'][$c['id']] ?? null);
            $heldIds = array_values(array_filter($subtree, fn ($x) => $x !== $c['id']));
            sort($heldIds);
            $conflicts[] = ['id' => $c['id'], 'kind' => $c['kind'], 'type' => $B['nodes'][$c['id']]['type'], 'base' => $b, 'ours' => $l, 'theirs' => $r, 'fields' => self::fieldChanges($b, $l, $r), 'held' => $heldIds];
        }

        /* 2. every other element: which side's data and place it takes */
        $decided = [];
        $take = function (string $side, string $id, string $kind) use (&$fromOurs, &$fromTheirs): void {
            if ($side === 'ours') {
                $fromOurs[] = ['id' => $id, 'kind' => $kind];
            } else {
                $fromTheirs[] = ['id' => $id, 'kind' => $kind];
            }
        };
        foreach ($ids as $id) {
            $b = $B['nodes'][$id] ?? null;
            $l = $L['nodes'][$id] ?? null;
            $r = $R['nodes'][$id] ?? null;
            if ($id === self::DOC) {
                $own = $edited($l, $b) && !$edited($r, $b) ? $l : (!$edited($l, $b) && $edited($r, $b) ? $r : $l);
                $decided[$id] = ['own' => $own, 'parent' => '', 'slot' => ''];
                if ($edited($l, $b) && $edited($r, $b) && $l['norm'] !== $r['norm']) {
                    $conflicts[] = ['id' => $id, 'kind' => 'edit-edit', 'type' => 'doc', 'base' => $raw($b), 'ours' => $raw($l), 'theirs' => $raw($r), 'fields' => self::fieldChanges($raw($b), $raw($l), $raw($r)), 'held' => []];
                }
                continue;
            }
            if (isset($held[$id])) {
                if ($l !== null) {
                    $decided[$id] = ['own' => $l, 'parent' => $l['parent'], 'slot' => $l['slot']];
                }
                continue;
            }
            if ($b === null) {
                if ($l !== null && $r !== null) {
                    $decided[$id] = ['own' => $l, 'parent' => $l['parent'], 'slot' => $l['slot']];
                    if (!($l['norm'] === $r['norm'] && self::placed($l, $r))) {
                        $conflicts[] = ['id' => $id, 'kind' => 'add-add', 'type' => $l['type'], 'base' => null, 'ours' => $raw($l), 'theirs' => $raw($r), 'fields' => self::fieldChanges(null, $raw($l), $raw($r)), 'held' => []];
                    }
                } elseif ($l !== null) {
                    $decided[$id] = ['own' => $l, 'parent' => $l['parent'], 'slot' => $l['slot']];
                    $take('ours', $id, 'added');
                } elseif ($r !== null) {
                    $decided[$id] = ['own' => $r, 'parent' => $r['parent'], 'slot' => $r['slot']];
                    $take('theirs', $id, 'added');
                }
                continue;
            }
            if ($l === null && $r === null) {
                continue;
            }
            if ($l === null || $r === null) {
                $take($l !== null ? 'theirs' : 'ours', $id, 'removed');
                continue;
            }
            $lc = $edited($l, $b);
            $rc = $edited($r, $b);
            $lm = $moved($l, $b);
            $rm = $moved($r, $b);
            $own = $l;
            $kind = null;
            if ($lc && $rc) {
                if ($l['norm'] !== $r['norm']) {
                    $kind = 'edit-edit';
                }
            } elseif ($rc) {
                $own = $r;
                $take('theirs', $id, 'changed');
            } elseif ($lc) {
                $take('ours', $id, 'changed');
            }
            $at = ['parent' => $l['parent'], 'slot' => $l['slot']];
            if ($lm && $rm) {
                if (!self::placed($l, $r)) {
                    $kind ??= 'move-move';
                }
            } elseif ($rm) {
                $at = ['parent' => $r['parent'], 'slot' => $r['slot']];
                $take('theirs', $id, 'moved');
            } elseif ($lm) {
                $take('ours', $id, 'moved');
            }
            $decided[$id] = ['own' => $own] + $at;
            if ($kind !== null) {
                $conflicts[] = ['id' => $id, 'kind' => $kind, 'type' => $l['type'], 'base' => $raw($b), 'ours' => $raw($l), 'theirs' => $raw($r), 'fields' => self::fieldChanges($raw($b), $raw($l), $raw($r)), 'held' => []];
            }
        }

        /* 3. members of every container, reachability from the root, one element per single slot */
        $dropped = [];
        $members = [];
        $conflicted = array_flip(array_column($conflicts, 'id'));
        foreach ($decided as $id => $d) {
            if ($id !== self::DOC) {
                $members[self::kidsKey($d['parent'], $d['slot'])][$id] = true;
            }
        }
        $out = ['nodes' => [self::DOC => ['parent' => '', 'slot' => ''] + $decided[self::DOC]['own']], 'kids' => []];
        $notes = [];
        $emit = function (string $parent) use (&$emit, &$out, &$dropped, &$conflicts, &$conflicted, &$notes, $members, $decided, $B, $L, $R, $raw): void {
            foreach (self::SLOTS[$out['nodes'][$parent]['type']] ?? [] as $slot => [$childType, $many]) {
                $set = $members[self::kidsKey($parent, $slot)] ?? [];
                if ($set === []) {
                    continue;
                }
                $list = array_keys($set);
                if (!$many && count($list) > 1) {
                    $keep = $list[0];
                    foreach ($list as $x) {
                        if (isset($L['nodes'][$x]) && $decided[$x]['own'] === $L['nodes'][$x]) {
                            $keep = $x;
                            break;
                        }
                    }
                    foreach ($list as $x) {
                        if ($x === $keep) {
                            continue;
                        }
                        $dropped[$x] = true;
                        if (!isset($conflicted[$x])) {
                            $conflicts[] = ['id' => $x, 'kind' => 'slot', 'type' => $decided[$x]['own']['type'], 'base' => $raw($B['nodes'][$x] ?? null), 'ours' => $raw($L['nodes'][$x] ?? null), 'theirs' => $raw($R['nodes'][$x] ?? null), 'fields' => [], 'held' => []];
                            $conflicted[$x] = true;
                        }
                    }
                    $list = [$keep];
                }
                $order = $list;
                if ($many) {
                    $merged = self::mergeOrder($B['kids'][self::kidsKey($parent, $slot)] ?? [], $L['kids'][self::kidsKey($parent, $slot)] ?? [], $R['kids'][self::kidsKey($parent, $slot)] ?? [], array_keys($set));
                    $order = $merged['order'];
                    if ($merged['both']) {
                        $notes[] = ['code' => 'ORDER_BOTH', 'id' => $parent, 'message' => "Both sides reordered the {$slot} of {$parent}; the server's order was kept and your new elements were placed after their neighbours."];
                    }
                }
                foreach ($order as $id) {
                    if (isset($dropped[$id])) {
                        continue;
                    }
                    $d = $decided[$id];
                    $out['nodes'][$id] = ['parent' => $parent, 'slot' => $slot] + $d['own'];
                    $out['kids'][self::kidsKey($parent, $slot)][] = $id;
                    $emit($id);
                }
            }
        };
        $emit(self::DOC);
        foreach ($decided as $id => $d) {
            if ($id === self::DOC || isset($out['nodes'][$id]) || isset($dropped[$id])) {
                continue;
            }
            // an element whose parent did not make it into the merge: report it instead of losing it quietly
            $explained = false;
            $seen = [];
            for ($cur = $d['parent']; $cur !== '' && !isset($seen[$cur]); $cur = $decided[$cur]['parent'] ?? '') {
                $seen[$cur] = true;
                if (isset($conflicted[$cur]) || isset($held[$cur])) {
                    $explained = true;
                    break;
                }
            }
            if (!$explained && !isset($conflicted[$id])) {
                $conflicts[] = ['id' => $id, 'kind' => 'orphan', 'type' => $d['own']['type'], 'base' => $raw($B['nodes'][$id] ?? null), 'ours' => $raw($L['nodes'][$id] ?? null), 'theirs' => $raw($R['nodes'][$id] ?? null), 'fields' => [], 'held' => []];
                $conflicted[$id] = true;
            }
        }

        return [
            'doc' => self::build($out),
            'conflicts' => $this->sorted($conflicts),
            'notes' => $notes,
            'fromOurs' => $fromOurs,
            'fromTheirs' => $fromTheirs,
        ];
    }

    private static function placed(array $a, array $b): bool
    {
        return $a['parent'] === $b['parent'] && $a['slot'] === $b['slot'];
    }

    /** @return string[] the element, what it held in the base and what the keeping side holds below it */
    private function subtree(string $id, array $keeper): array
    {
        $out = [$id => true];
        foreach (self::descendants($this->B, $id) as $d) {
            $out[$d] = true;
        }
        foreach (array_keys($keeper['nodes']) as $k) {
            if (self::below($keeper, $k, $id)) {
                $out[$k] = true;
            }
        }

        return array_keys($out);
    }

    /** @return string[] */
    private static function descendants(array $t, string $id): array
    {
        $out = [];
        $walk = function (string $parent) use (&$walk, &$out, $t): void {
            foreach (array_keys(self::SLOTS[$t['nodes'][$parent]['type'] ?? ''] ?? []) as $slot) {
                foreach ($t['kids'][self::kidsKey($parent, $slot)] ?? [] as $k) {
                    $out[] = $k;
                    $walk($k);
                }
            }
        };
        $walk($id);

        return $out;
    }

    /** True when the element is `$ancestor` or sits below it. */
    private static function below(array $t, string $id, string $ancestor): bool
    {
        $seen = [];
        for ($cur = $id; $cur !== '' && !isset($seen[$cur]); $cur = $t['nodes'][$cur]['parent'] ?? '') {
            if ($cur === $ancestor) {
                return true;
            }
            $seen[$cur] = true;
        }

        return false;
    }

    /** @return array<int,array{field:string,base:mixed,ours:mixed,theirs:mixed}> */
    private static function fieldChanges(?array $base, ?array $ours, ?array $theirs): array
    {
        $keys = array_unique([...array_keys($base ?? []), ...array_keys($ours ?? []), ...array_keys($theirs ?? [])]);
        sort($keys, SORT_STRING);
        $text = fn (?array $o, string $k) => self::canon(self::normalise([$k => $o[$k] ?? null]));
        $out = [];
        foreach ($keys as $field) {
            $field = (string) $field;
            if ($ours !== null && $theirs !== null && $text($ours, $field) === $text($theirs, $field)) {
                continue;
            }
            $out[] = ['field' => $field, 'base' => $base[$field] ?? null, 'ours' => $ours[$field] ?? null, 'theirs' => $theirs[$field] ?? null];
        }

        return $out;
    }

    /**
     * The order of a list whose members came from three lists: the side that reordered wins, the
     * server's order when both did, and elements only one side has are placed after their neighbour.
     *
     * @param string[] $b
     * @param string[] $l
     * @param string[] $r
     * @param string[] $members
     * @return array{order:string[],both:bool}
     */
    private static function mergeOrder(array $b, array $l, array $r, array $members): array
    {
        $set = array_flip($members);
        $only = fn (array $list) => array_values(array_filter($list, fn ($x) => isset($set[$x])));
        [$bb, $ll, $rr] = [$only($b), $only($l), $only($r)];
        $same = function (array $x, array $y): bool {
            $common = array_intersect($x, $y);
            $a = array_values(array_filter($x, fn ($e) => in_array($e, $common, true)));
            $c = array_values(array_filter($y, fn ($e) => in_array($e, $common, true)));

            return $a === $c;
        };
        $lMoved = !$same($bb, $ll);
        $rMoved = !$same($bb, $rr);
        $primaryIsL = $lMoved && !$rMoved;
        $primary = $primaryIsL ? $ll : $rr;
        $secondary = $primaryIsL ? $rr : $ll;
        $result = $primary;
        $placed = array_flip($result);
        foreach ($secondary as $at => $m) {
            if (isset($placed[$m])) {
                continue;
            }
            $anchor = -1;
            for ($i = $at - 1; $i >= 0; $i--) {
                $p = array_search($secondary[$i], $result, true);
                if ($p !== false) {
                    $anchor = $p;
                    break;
                }
            }
            array_splice($result, $anchor + 1, 0, [$m]);
            $placed = array_flip($result);
        }
        foreach ($members as $m) {
            if (!isset($placed[$m])) {
                $result[] = $m;
            }
        }

        return ['order' => $result, 'both' => $lMoved && $rMoved && !$same($ll, $rr)];
    }

    /** Conflicts in document order (ours first, then theirs, then base). */
    private function sorted(array $conflicts): array
    {
        $position = function (string $id): int {
            foreach ([$this->L, $this->R, $this->B] as $t) {
                $n = array_search($id, array_keys($t['nodes']), true);
                if ($n !== false) {
                    return (int) $n;
                }
            }

            return PHP_INT_MAX;
        };
        usort($conflicts, fn ($a, $b) => $position($a['id']) <=> $position($b['id']));

        return $conflicts;
    }
}
