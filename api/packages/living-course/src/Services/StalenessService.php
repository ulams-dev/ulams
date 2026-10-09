<?php

namespace Ulams\LivingCourse\Services;

use Ulams\CourseBuilder\Models\Session;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\ElementStatus;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\Revision;

/** Staleness signals per element and per course (plan 10.1). */
final class StalenessService
{
    /**
     * @param array<int,array<string,mixed>> $items impact items (element_id, element_type, label, fragment_ids, answer_check, removed)
     */
    public function markPending(Session $session, array $items, ?string $proposalId, ?\DateTimeInterface $since = null): void
    {
        foreach ($items as $item) {
            ElementStatus::query()->updateOrCreate(
                ['session_id' => $session->id, 'element_id' => $item['element_id']],
                [
                    'status' => ($item['removed'] ?? false) ? 'source_removed' : 'pending',
                    'element_type' => $item['element_type'],
                    'label' => mb_substr((string) $item['label'], 0, 160),
                    'since' => $since ?? now(),
                    'proposal_id' => $proposalId,
                    'fragment_ids' => $item['fragment_ids'],
                    'answer_check' => (bool) ($item['answer_check'] ?? false),
                    'updated_at' => now(),
                ],
            );
        }
    }

    /** @param string[] $elementIds */
    public function set(Session $session, array $elementIds, string $status): void
    {
        if ($elementIds === []) {
            return;
        }
        ElementStatus::query()->where('session_id', $session->id)->whereIn('element_id', $elementIds)
            ->update(['status' => $status, 'updated_at' => now(), 'answer_check' => false]);
    }

    /** Every still-open element of the session becomes `$status` (a proposal was settled as a whole). */
    public function settleOpen(Session $session, string $status): void
    {
        ElementStatus::query()->where('session_id', $session->id)->whereIn('status', ['pending', 'source_removed'])
            ->update(['status' => $status, 'updated_at' => now(), 'answer_check' => false]);
    }

    /** @return array<string,array<string,mixed>> element id => status row, for open and dismissed elements */
    public function elements(Session $session): array
    {
        return ElementStatus::query()->where('session_id', $session->id)->where('status', '!=', 'in_sync')->get()->mapWithKeys(fn (ElementStatus $e) => [$e->element_id => [
            'elementId' => $e->element_id,
            'status' => $e->status,
            'type' => $e->element_type,
            'label' => $e->label,
            'since' => $e->since?->toIso8601String(),
            'proposalId' => $e->proposal_id,
            'fragmentIds' => $e->fragment_ids ?? [],
            'answerCheck' => $e->answer_check,
        ]])->all();
    }

    /**
     * The course summary: `in_sync`, `stale` (open elements or an open proposal) or `dismissed`.
     *
     * @return array<string,mixed>
     */
    public function summary(Session $session): array
    {
        $connections = Connection::query()->where('session_id', $session->id)->get();
        $open = ElementStatus::query()->where('session_id', $session->id)->whereIn('status', ['pending', 'source_removed']);
        $pending = (clone $open)->count();
        $since = (clone $open)->min('since');
        $proposal = Proposal::query()->where('session_id', $session->id)->whereIn('status', Proposal::OPEN)->latest('created_at')->first();
        $dismissed = ElementStatus::query()->where('session_id', $session->id)->where('status', 'dismissed')->exists();
        $synced = $connections->map(fn (Connection $c) => $c->synced_revision_id ? Revision::query()->find($c->synced_revision_id) : null)->filter()->max('number');
        $latest = $connections->map(fn (Connection $c) => $c->latest_revision_id ? Revision::query()->find($c->latest_revision_id) : null)->filter()->max('number');
        $state = $pending > 0 || $proposal !== null ? 'stale' : ($dismissed ? 'dismissed' : 'in_sync');
        $sinceAt = $since !== null ? \Illuminate\Support\Carbon::parse($since) : $proposal?->created_at;

        return [
            'state' => $state,
            'since' => $sinceAt?->toIso8601String(),
            'days' => $sinceAt !== null ? (int) $sinceAt->diffInDays(now()) : null,
            'pendingElements' => $pending,
            'openProposalId' => $proposal?->id,
            'syncedRevision' => $synced,
            'latestRevision' => $latest,
            'lastCheckedAt' => $connections->max('last_checked_at')?->toIso8601String(),
            'tracked' => $connections->isNotEmpty(),
        ];
    }
}
