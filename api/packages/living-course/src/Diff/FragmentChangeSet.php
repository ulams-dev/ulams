<?php

namespace Ulams\LivingCourse\Diff;

/**
 * The result of comparing two revisions: the fragment-level changes in document order.
 *
 * A change is `['kind' => changed|moved|removed|added, 'old' => ?string, 'new' => ?string,
 * 'magnitude' => trivial|minor|substantive, 'similarity' => float, 'signals' => string[],
 * 'word_diff' => ?array]` (ids are fragment ids).
 */
final class FragmentChangeSet
{
    /** @param array<int,array<string,mixed>> $changes */
    public function __construct(
        public readonly array $changes,
        public readonly bool $unchanged = false,
    ) {
    }

    /** @return array<int,array<string,mixed>> */
    public function significant(): array
    {
        return array_values(array_filter($this->changes, fn (array $c) => !($c['kind'] === 'changed' && $c['magnitude'] === 'trivial')));
    }

    /**
     * No change that needs a decision: every change is trivial and keeps its fragment id.
     * Citations stay valid and no content changed, so the revision can be accepted silently.
     */
    public function isNoImpact(): bool
    {
        foreach ($this->changes as $c) {
            if ($c['kind'] === 'removed' || $c['kind'] === 'added' || $c['magnitude'] !== 'trivial' || $c['old'] !== $c['new']) {
                return false;
            }
        }

        return true;
    }

    /** @return array{changed:int,moved:int,removed:int,added:int,trivial:int,minor:int,substantive:int,total:int} changed (not trivial), moved, removed, added; trivial counts cosmetic edits apart */
    public function counts(): array
    {
        $counts = ['changed' => 0, 'moved' => 0, 'removed' => 0, 'added' => 0, 'trivial' => 0, 'minor' => 0, 'substantive' => 0, 'total' => count($this->changes)];
        foreach ($this->changes as $c) {
            if ($c['kind'] === 'changed' && $c['magnitude'] === 'trivial') {
                $counts['trivial']++;
                continue;
            }
            $counts[$c['kind']]++;
            if (in_array($c['magnitude'], ['minor', 'substantive'], true) && in_array($c['kind'], ['changed', 'moved'], true)) {
                $counts[$c['magnitude']]++;
            }
        }

        return $counts;
    }
}
