<?php

namespace Ulams\LivingCourse\Support;

use Ulams\CourseBuilder\Models\Source;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Models\RevisionFragment;

/** API shapes (camelCase) of Living Course rows. Secrets never leave the server. */
final class Presenter
{
    /** @return array<string,mixed> */
    public static function revision(Revision $r, ?Connection $connection = null): array
    {
        $meta = (array) $r->metadata;

        return [
            'id' => $r->id,
            'sourceId' => $r->source_id,
            'number' => $r->number,
            'origin' => $r->origin,
            'originRef' => $r->origin_ref,
            'trigger' => $r->trigger,
            'triggeredBy' => $r->triggered_by,
            'status' => $r->status,
            'fragmentCount' => $r->fragment_count,
            'tokens' => $r->token_estimate,
            'title' => $meta['title'] ?? null,
            'name' => $meta['name'] ?? null,
            'files' => $meta['files'] ?? null,
            'counts' => $meta['counts'] ?? null,
            'error' => $r->error,
            'detectedAt' => ($r->detected_at ?? $r->created_at)?->toIso8601String(),
            'synced' => $connection !== null ? $connection->synced_revision_id === $r->id : null,
            'latest' => $connection !== null ? $connection->latest_revision_id === $r->id : null,
        ];
    }

    /** @return array<string,mixed> */
    public static function connection(Connection $c): array
    {
        $synced = $c->synced_revision_id ? Revision::query()->find($c->synced_revision_id) : null;
        $latest = $c->latest_revision_id ? Revision::query()->find($c->latest_revision_id) : null;

        return [
            'id' => $c->id,
            'connector' => $c->connector,
            'schedule' => $c->schedule,
            'status' => $c->status,
            'autoAnalyse' => $c->auto_analyse,
            'settings' => $c->settings ?? [],
            'config' => $c->config ?? [],
            'syncedRevision' => $synced ? ['id' => $synced->id, 'number' => $synced->number] : null,
            'latestRevision' => $latest ? ['id' => $latest->id, 'number' => $latest->number, 'status' => $latest->status] : null,
            'lastCheckedAt' => $c->last_checked_at?->toIso8601String(),
            'nextCheckAt' => $c->next_check_at?->toIso8601String(),
            'lastChangeAt' => $c->last_change_at?->toIso8601String(),
            'failureCount' => $c->failure_count,
            'lastError' => $c->last_error,
            'secretsSet' => array_keys(array_filter((array) $c->secrets)),
        ];
    }

    /** @return array<string,mixed> */
    public static function source(Source $s, ?Connection $c): array
    {
        return [
            'id' => $s->id,
            'name' => $s->original_name,
            'status' => $s->status,
            'kind' => $s->kind(),
            'size' => $s->size,
            'tokens' => $s->token_estimate,
            'fragments' => (int) ($s->metadata['fragments'] ?? 0),
            'title' => $s->metadata['title'] ?? null,
            'connection' => $c !== null ? self::connection($c) : null,
            'revisionCount' => $c !== null ? $c->revisions()->count() : 0,
        ];
    }

    /**
     * Changes between two revisions with the old and new fragment text.
     *
     * @param array<int,array<string,mixed>> $changes rows as stored by ChangeDetection (kind, old, new, magnitude, similarity, signals, word_diff)
     * @return array<int,array<string,mixed>>
     */
    public static function changes(Revision $from, Revision $to, array $changes): array
    {
        $fragment = function (?string $id, Revision $revision) {
            if ($id === null) {
                return null;
            }
            $f = RevisionFragment::query()->where('revision_id', $revision->id)->where('fragment_id', $id)->first();

            return $f === null ? null : [
                'fragmentId' => $f->fragment_id, 'label' => $f->label(), 'section' => $f->section, 'headingPath' => $f->heading_path,
                'file' => $f->file_path, 'text' => mb_substr($f->text, 0, 6000), 'pages' => $f->page_start ? [$f->page_start, $f->page_end] : null,
            ];
        };

        return array_map(fn (array $c, int $i) => [
            'id' => $c['id'] ?? $i + 1,
            'kind' => $c['kind'],
            'magnitude' => $c['magnitude'],
            'similarity' => (float) $c['similarity'],
            'signals' => $c['signals'] ?? [],
            'wordDiff' => $c['word_diff'] ?? null,
            'old' => $fragment($c['old'], $from),
            'new' => $fragment($c['new'], $to),
        ], $changes, array_keys($changes));
    }

    /** @return array<string,mixed> */
    public static function proposalSummary(Proposal $p): array
    {
        $from = Revision::query()->find($p->from_revision_id);
        $to = Revision::query()->find($p->to_revision_id);

        return [
            'id' => $p->id,
            'number' => $p->number,
            'sessionId' => $p->session_id,
            'sourceId' => $p->source_id,
            'status' => $p->status,
            'trigger' => $p->trigger,
            'counts' => $p->counts,
            'fromRevision' => $from ? ['id' => $from->id, 'number' => $from->number] : null,
            'toRevision' => $to ? ['id' => $to->id, 'number' => $to->number, 'detectedAt' => $to->detected_at?->toIso8601String()] : null,
            'baseVersionId' => $p->base_version_id,
            'resultVersionId' => $p->result_version_id,
            'estimatedCostMicroUsd' => $p->estimated_cost_micro_usd,
            'costMicroUsd' => $p->cost_micro_usd,
            'decisions' => ProposalItem::query()->where('proposal_id', $p->id)->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all(),
            'learnerNote' => $p->learner_note,
            'error' => $p->error,
            'createdAt' => $p->created_at?->toIso8601String(),
            'decidedAt' => $p->decided_at?->toIso8601String(),
            'appliedAt' => $p->applied_at?->toIso8601String(),
        ];
    }

    /**
     * @param array<string,string> $labels fragment id => label
     * @return array<string,mixed>
     */
    public static function item(ProposalItem $i, array $labels = []): array
    {
        return [
            'id' => $i->id,
            'groupKey' => $i->group_key,
            'elementId' => $i->element_id,
            'type' => $i->element_type,
            'label' => $i->label,
            'kind' => $i->kind,
            'reason' => $i->reason,
            'severity' => $i->severity,
            'before' => $i->before,
            'after' => $i->after,
            'changeClass' => $i->change_class,
            'answerStatus' => $i->answer_status,
            'answerCheck' => $i->answer_check,
            'status' => $i->status,
            'flags' => $i->flags ?? [],
            'regenerations' => $i->regenerations,
            'fragments' => array_map(fn ($id) => ['fragmentId' => $id, 'label' => $labels[$id] ?? $id], (array) $i->fragment_ids),
            'changeIds' => $i->change_ids ?? [],
            'decidedAt' => $i->decided_at?->toIso8601String(),
        ];
    }
}
