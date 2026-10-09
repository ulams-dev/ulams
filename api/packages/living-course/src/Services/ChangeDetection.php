<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Ulams\LivingCourse\Diff\FragmentChangeSet;
use Ulams\LivingCourse\Diff\FragmentDiff;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\FragmentChange;
use Ulams\LivingCourse\Models\Revision;

/**
 * What happens to a freshly stored revision (ADR 0031): it is compared with the synced revision,
 * the changes are stored, and the revision is classified. A revision whose text equals the synced
 * one is `unchanged`; one that only has cosmetic changes with unchanged fragment ids is `no_impact`
 * and is accepted silently; anything else stays `ingested` and waits for impact analysis.
 */
final class ChangeDetection
{
    public function __construct(
        private readonly FragmentDiff $diff,
        private readonly RevisionService $revisions,
        private readonly AuditLog $audit,
    ) {
    }

    /** @return array{revision:Revision,changes:?FragmentChangeSet} */
    public function process(Revision $to, ?int $userId = null): array
    {
        $connection = Connection::query()->findOrFail($to->connection_id);
        $from = $connection->synced_revision_id !== null && $connection->synced_revision_id !== $to->id
            ? Revision::query()->find($connection->synced_revision_id) : null;
        if ($from === null) {
            return ['revision' => $to, 'changes' => null];
        }
        $context = [
            'session_id' => $connection->session_id, 'subject_type' => 'revision', 'subject_id' => $to->id, 'source_id' => $to->source_id,
            'revision_id' => $to->id, 'origin_ref' => $to->origin_ref, ...($userId === null ? ['actor_type' => 'system'] : ['actor_id' => $userId]),
        ];

        if ($from->normalised_sha256 === $to->normalised_sha256) {
            $this->finish($to, 'unchanged', ['counts' => (new FragmentChangeSet([], true))->counts(), 'against' => $from->number]);
            $connection->forceFill(['latest_revision_id' => $to->id, 'last_checked_at' => now()])->save();

            return ['revision' => $to->refresh(), 'changes' => new FragmentChangeSet([], true)];
        }

        try {
            $changes = $this->diff->compareRevisions($from, $to);
        } catch (RuntimeException $e) {
            $to->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
            $this->audit->record('revision.failed', $context + ['data' => ['reason' => mb_substr($e->getMessage(), 0, 300)]]);

            throw $e;
        }

        DB::transaction(function () use ($to, $from, $changes, $connection, $context) {
            FragmentChange::query()->where('to_revision_id', $to->id)->delete();
            foreach (array_chunk($changes->changes, 200) as $chunk) {
                FragmentChange::query()->insert(array_map(fn (array $c) => [
                    'from_revision_id' => $from->id,
                    'to_revision_id' => $to->id,
                    'kind' => $c['kind'],
                    'old_fragment_id' => $c['old'],
                    'new_fragment_id' => $c['new'],
                    'magnitude' => $c['magnitude'],
                    'similarity' => $c['similarity'],
                    'signals' => $c['signals'] === [] ? null : json_encode($c['signals']),
                    'word_diff' => $c['word_diff'] === null ? null : json_encode($c['word_diff'], JSON_UNESCAPED_UNICODE),
                ], $chunk));
            }
            $counts = $changes->counts();
            $noImpact = $changes->isNoImpact();
            $this->finish($to, $noImpact ? 'no_impact' : 'ingested', ['counts' => $counts, 'against' => $from->number]);
            $connection->forceFill(['latest_revision_id' => $to->id, 'last_checked_at' => now(), 'last_change_at' => now()])->save();
            $data = ['counts' => $counts, 'from' => $from->number, 'to' => $to->number];
            if ($noImpact) {
                $this->audit->record('revision.no_impact', $context + ['data' => $data]);
                $this->revisions->promote($to->refresh(), null);
            } else {
                $this->audit->record('revision.detected', $context + ['data' => $data]);
            }
        });

        return ['revision' => $to->refresh(), 'changes' => $changes];
    }

    /** @param array<string,mixed> $meta */
    private function finish(Revision $revision, string $status, array $meta): void
    {
        $revision->forceFill(['status' => $status, 'metadata' => array_merge((array) $revision->metadata, $meta)])->save();
    }
}
