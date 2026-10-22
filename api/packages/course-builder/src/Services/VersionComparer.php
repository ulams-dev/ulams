<?php

namespace Ulams\CourseBuilder\Services;

use Illuminate\Support\Facades\Cache;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\SemanticDiff;
use Ulams\CourseBuilder\Contracts\SourceHistory;
use Ulams\CourseBuilder\Models\Fragment;
use Ulams\CourseBuilder\Models\Version;

/**
 * The compare payload of two versions of one session (ADR 0192, CV-2): `SemanticDiff` plus the source passages behind
 * every citation that changed, cached per pair. Versions are immutable, so a cached payload never goes stale. The source
 * history (Living Course, through the `SourceHistory` contract) supplies repointed citations and the passage as it was in a
 * source revision; without it a citation that changed is plain added or removed and the passage is the one stored for the
 * fragment.
 */
final class VersionComparer
{
    public const CACHE_SECONDS = 86400;

    private const PASSAGE_CHARS = 600;

    public function __construct(private readonly SourceHistory $history)
    {
    }

    /** @return array<string,mixed> the SemanticDiff payload with `fragments` */
    public function compare(Version $from, Version $to): array
    {
        return Cache::remember("course-builder:compare:v" . SemanticDiff::VERSION . ":{$from->id}:{$to->id}", self::CACHE_SECONDS, fn () => $this->build($from, $to));
    }

    /** @return array<string,mixed> */
    public function build(Version $from, Version $to): array
    {
        $payload = SemanticDiff::compare($from->document, $to->document, $this->repointed($from->document, $to->document));
        $payload['fragments'] = $this->passages($payload, $from, $to);

        return $payload;
    }

    /**
     * Fragments that a citation moved from, as the fragment it moved to in a newer source revision. Not only the ids that
     * left or entered the whole document: a citation can move to a fragment that another element already cites.
     *
     * @return array<string,string>
     */
    private function repointed(array $from, array $to): array
    {
        $cited = array_values(array_unique([...Blueprint::citations($from), ...Blueprint::citations($to)]));

        return count($cited) < 2 ? [] : $this->history->repointed($cited);
    }

    /**
     * The passage and source revision behind each citation that changed: a citation that went away is read as it was in the
     * `from` version's source revisions, one that came in as it is in the `to` version's.
     *
     * @return array<string,array{label:string,text:string,sourceRevision:array{id:string,number:int|null}|null}>
     */
    private function passages(array $payload, Version $from, Version $to): array
    {
        $sides = [];
        foreach ($payload['changes'] as $c) {
            foreach ($c['citations']['removed'] ?? [] as $id) {
                $sides[$id] = $from;
            }
            foreach ($c['citations']['added'] ?? [] as $id) {
                $sides[$id] = $to;
            }
            foreach ($c['citations']['repointed'] ?? [] as $pair) {
                $sides[$pair['from']] = $from;
                $sides[$pair['to']] = $to;
            }
        }
        $out = [];
        foreach ($sides as $id => $version) {
            $out[$id] = $this->passage($id, $version);
        }

        return $out;
    }

    private function passage(string $id, Version $version): array
    {
        $revisions = array_values(array_filter((array) ($version->source_revisions ?? []), 'is_string'));
        $found = $revisions !== [] ? $this->history->passage($id, $revisions) : null;
        if ($found !== null) {
            return [
                'label' => mb_substr($found['label'], 0, 120),
                'text' => mb_substr($found['text'], 0, self::PASSAGE_CHARS),
                'sourceRevision' => ['id' => $found['revisionId'], 'number' => $found['revisionNumber']],
            ];
        }
        $fragment = Fragment::query()->find($id);

        return [
            'label' => $fragment !== null ? mb_substr($fragment->label(), 0, 120) : $id,
            'text' => $fragment !== null ? mb_substr($fragment->text, 0, self::PASSAGE_CHARS) : '',
            'sourceRevision' => null,
        ];
    }
}
