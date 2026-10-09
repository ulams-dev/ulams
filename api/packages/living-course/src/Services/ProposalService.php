<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Support\Facades\DB;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\LivingCourse\Diff\ImpactAnalyzer;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Models\FragmentChange;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Models\RevisionFragment;

/**
 * Creates update proposals (ADR 0030): the deterministic part. A proposal exists for every revision
 * that touches cited text; its items are citation remaps (accepted by default), `manual` items for
 * every impacted element (what the AI analysis later turns into patches), and `uncovered` sections.
 * Works with AI disabled: the author then edits the marked elements by hand.
 */
final class ProposalService
{
    public function __construct(
        private readonly ImpactAnalyzer $impact,
        private readonly StalenessService $staleness,
        private readonly AuditLog $audit,
        private readonly RevisionService $revisions,
        private readonly EventLog $events,
        private readonly LlmClient $llm,
        private readonly AnalysisService $analysis,
    ) {
    }

    /**
     * @return array{proposal:?Proposal,state:string} state: created | kept_existing | no_impact | not_applicable | deferred
     */
    public function createFor(Revision $to, string $trigger = 'manual', ?int $userId = null): array
    {
        $connection = Connection::query()->findOrFail($to->connection_id);
        $session = Session::query()->find($connection->session_id);
        $from = Revision::query()->find($connection->synced_revision_id);
        $current = $session?->currentVersion;
        if ($session === null || $from === null || $current === null || $current->status !== Version::APPROVED || !in_array($current->kind, VersionService::CONTENT_KINDS, true)) {
            // no generated course yet: nothing cites the source, the next build reads the live fragments
            return ['proposal' => null, 'state' => 'not_applicable'];
        }
        $today = Proposal::query()->where('source_id', $connection->source_id)->where('created_at', '>=', now()->startOfDay())->count();
        if ($today >= (int) config('living_course.cost.proposals_per_source_per_day', 5) && !Proposal::query()->where('to_revision_id', $to->id)->exists()) {
            // later revisions wait for the next day; a manual check processes them then
            $to->forceFill(['metadata' => array_merge((array) $to->metadata, ['deferred' => true])])->save();

            return ['proposal' => null, 'state' => 'deferred'];
        }
        $kept = $this->supersedeOpen($connection, $to, $userId);
        if ($kept instanceof Proposal) {
            return ['proposal' => $kept, 'state' => 'kept_existing'];
        }

        $changes = FragmentChange::query()->where('to_revision_id', $to->id)->orderBy('id')->get()->map(fn (FragmentChange $c) => [
            'id' => $c->id, 'kind' => $c->kind, 'old' => $c->old_fragment_id, 'new' => $c->new_fragment_id, 'magnitude' => $c->magnitude,
            'similarity' => $c->similarity, 'signals' => $c->signals ?? [],
        ])->all();
        $labels = [];
        foreach ([$from, $to] as $revision) {
            foreach (RevisionFragment::query()->where('revision_id', $revision->id)->get(['fragment_id', 'section', 'heading_path', 'file_path']) as $f) {
                $labels[$f->fragment_id] ??= $f->label();
            }
        }
        $analysis = $this->impact->analyse($current->document, $changes, $labels);
        $elements = $this->impact->elements($current->document);
        $actionable = array_filter($analysis['items'], fn ($i) => $i['kind'] === 'manual');

        if ($analysis['items'] === []) {
            // nothing in the course cites a changed section: accept the revision, keep the uncovered note
            $proposal = $this->persist($session, $connection, $from, $to, $current, $analysis, 'no_impact', $trigger, $userId, $elements);
            $to->forceFill(['status' => 'no_impact'])->save();
            $this->audit->record('revision.no_impact', $this->ctx($session, $to, $userId) + ['data' => ['reason' => 'no course element cites a changed section', 'uncovered' => count($analysis['uncovered'])]]);
            $this->revisions->promote($to->refresh(), $userId);

            return ['proposal' => $proposal, 'state' => 'no_impact'];
        }

        $status = $actionable === [] ? 'ready' : ($this->llm->enabled() ? 'awaiting_analysis' : 'ready');
        $proposal = $this->persist($session, $connection, $from, $to, $current, $analysis, $status, $trigger, $userId, $elements);
        $this->announce($session, $proposal);
        if ($status === 'awaiting_analysis' && $connection->auto_analyse) {
            $this->analysis->begin($proposal, false, $userId);
            $proposal->refresh();
        }

        return ['proposal' => $proposal, 'state' => 'created'];
    }

    /** A card in the session thread: the source changed, here is what is affected. */
    private function announce(Session $session, Proposal $proposal): void
    {
        $counts = (array) $proposal->counts;
        $this->events->custom($session, null, 'update_proposal', [
            'proposalId' => $proposal->id, 'number' => $proposal->number, 'status' => $proposal->status,
            'elements' => $counts['elements'] ?? 0, 'answerChecks' => $counts['answerChecks'] ?? 0, 'uncovered' => $counts['uncovered'] ?? 0,
        ]);
        $this->events->text($session, null, sprintf(
            'The source changed: %d course element%s may need an update%s. Review the update proposal when you are ready; nothing changes in the course until you approve it.',
            $counts['elements'] ?? 0,
            ($counts['elements'] ?? 0) === 1 ? '' : 's',
            ($counts['answerChecks'] ?? 0) > 0 ? sprintf(', and %d quiz answer%s may now be wrong', $counts['answerChecks'], $counts['answerChecks'] === 1 ? '' : 's') : '',
        ));
    }

    /**
     * One open proposal per source (decision 5). A newer revision supersedes an undecided proposal;
     * when the author already decided items the proposal stays and is returned.
     */
    private function supersedeOpen(Connection $connection, Revision $to, ?int $userId): ?Proposal
    {
        foreach (Proposal::query()->where('source_id', $connection->source_id)->whereIn('status', Proposal::OPEN)->orderBy('number')->get() as $open) {
            if ($open->to_revision_id === $to->id) {
                return $open;
            }
            $decided = ProposalItem::query()->where('proposal_id', $open->id)->whereNotNull('decided_by')->exists();
            if ($decided) {
                $open->forceFill(['counts' => array_merge((array) $open->counts, ['newerRevisionId' => $to->id, 'newerRevision' => $to->number])])->save();

                return $open;
            }
            $open->forceFill(['status' => 'superseded'])->save();
            $this->audit->record('proposal.superseded', [
                'session_id' => $open->session_id, 'subject_type' => 'proposal', 'subject_id' => $open->id, 'source_id' => $open->source_id,
                'revision_id' => $to->id, 'data' => ['number' => $open->number, 'by_revision' => $to->number],
                ...($userId === null ? ['actor_type' => 'system'] : ['actor_id' => $userId]),
            ]);
        }

        return null;
    }

    /** @param array<string,array<string,mixed>> $elements */
    private function persist(Session $session, Connection $connection, Revision $from, Revision $to, Version $current, array $analysis, string $status, string $trigger, ?int $userId, array $elements): Proposal
    {
        return DB::transaction(function () use ($session, $connection, $from, $to, $current, $analysis, $status, $trigger, $userId, $elements) {
            Session::query()->whereKey($session->id)->lockForUpdate()->first(['id']);
            $number = (int) Proposal::query()->where('session_id', $session->id)->max('number') + 1;
            $items = $analysis['items'];
            $proposal = Proposal::query()->create([
                'number' => $number,
                'session_id' => $session->id,
                'source_id' => $connection->source_id,
                'from_revision_id' => $from->id,
                'to_revision_id' => $to->id,
                'base_version_id' => $current->id,
                'status' => $status,
                'trigger' => $trigger,
                'created_by' => $userId,
                'counts' => $this->counts($analysis, $to),
            ]);
            $now = now();
            foreach ($items as $item) {
                $el = $elements[$item['element_id']];
                $remap = $item['kind'] === 'citation_remap';
                ProposalItem::query()->create([
                    'proposal_id' => $proposal->id,
                    'group_key' => $item['group_key'],
                    'element_id' => $item['element_id'],
                    'element_type' => $item['element_type'],
                    'label' => $item['label'],
                    'kind' => $item['kind'],
                    'change_ids' => $item['change_ids'],
                    'fragment_ids' => $item['fragment_ids'],
                    'reason' => $item['reason'],
                    'severity' => $item['severity'],
                    'before' => self::snapshot($el['type'], $el['node']),
                    'after' => $remap ? self::snapshot($el['type'], self::remapCitations($el['node'], $item['moves'] + $analysis['remap'], $item['removed'] ? $item['fragment_ids'] : [])) : null,
                    'change_class' => $remap ? 'none' : null,
                    'answer_check' => $item['answer_check'],
                    'status' => $remap ? 'accepted' : 'pending',
                    'flags' => $item['signals'] === [] ? null : ['signals' => $item['signals']],
                ]);
            }
            foreach ($analysis['uncovered'] as $u) {
                ProposalItem::query()->create([
                    'proposal_id' => $proposal->id,
                    'group_key' => 'uncovered',
                    'element_id' => $u['fragment'],
                    'element_type' => 'section',
                    'label' => mb_substr((string) $u['label'], 0, 160),
                    'kind' => 'uncovered',
                    'change_ids' => array_filter([$u['changeId']]),
                    'fragment_ids' => [$u['fragment']],
                    'reason' => 'New section in the source that no lesson covers yet.',
                    'severity' => 'minor',
                    'status' => 'pending',
                ]);
            }
            $this->staleness->markPending($session, array_filter($items, fn ($i) => $i['kind'] === 'manual'), $proposal->id, $to->detected_at);
            $this->audit->record('proposal.created', $this->ctx($session, $to, $userId) + [
                'subject_type' => 'proposal', 'subject_id' => $proposal->id, 'version_from' => $current->number,
                'data' => ['number' => $number, 'status' => $status, 'from' => $from->number, 'to' => $to->number] + $proposal->counts,
            ]);

            return $proposal;
        });
    }

    /** @return array<string,mixed> */
    private function counts(array $analysis, Revision $to): array
    {
        $items = $analysis['items'];
        $actionable = array_filter($items, fn ($i) => $i['kind'] !== 'citation_remap');
        $byType = [];
        foreach ($actionable as $i) {
            $byType[$i['element_type']] = ($byType[$i['element_type']] ?? 0) + 1;
        }

        return [
            'items' => count($items) + count($analysis['uncovered']),
            'elements' => count($actionable),
            'remaps' => count($items) - count($actionable),
            'uncovered' => count($analysis['uncovered']),
            'major' => count(array_filter($actionable, fn ($i) => $i['severity'] === 'major')),
            'answerChecks' => count(array_filter($actionable, fn ($i) => $i['answer_check'])),
            'groups' => count($analysis['groups']),
            'byType' => $byType,
            'source' => $to->metadata['counts'] ?? null,
        ];
    }

    /** @return array<string,mixed> */
    private function ctx(Session $session, Revision $to, ?int $userId): array
    {
        return ['session_id' => $session->id, 'source_id' => $to->source_id, 'revision_id' => $to->id, 'origin_ref' => $to->origin_ref]
            + ($userId === null ? ['actor_type' => 'system'] : ['actor_id' => $userId]);
    }

    /** What the review shows as "before": the element without bulky children. */
    private static function snapshot(string $type, array $node): array
    {
        if ($type === 'lesson') {
            return ['id' => $node['id'], 'title' => $node['title'], 'citations' => $node['citations'] ?? []];
        }
        if ($type === 'course') {
            return ['id' => $node['id'], 'title' => $node['title'], 'faq' => $node['faq'] ?? []];
        }

        return $node;
    }

    /**
     * Replaces moved fragment ids in the citations of an element (anywhere in its subtree) and,
     * for removed sections, drops the listed ids.
     *
     * @param array<string,string> $map old fragment id => new fragment id
     * @param string[] $drop fragment ids to remove from citation lists
     */
    public static function remapCitations(array $node, array $map, array $drop = []): array
    {
        $walk = function (array $value) use (&$walk, $map): array {
            foreach ($value as $key => $item) {
                if (is_array($item)) {
                    $value[$key] = $walk($item);
                } elseif ($key !== 'id' && is_string($item) && isset($map[$item])) {
                    $value[$key] = $map[$item];
                }
            }

            return $value;
        };
        $out = $walk($node);
        $dedupe = function (array $value) use (&$dedupe, $drop): array {
            foreach ($value as $key => $item) {
                if (is_array($item)) {
                    $value[$key] = $dedupe($item);
                }
            }
            if ($value !== [] && array_is_list($value) && count(array_filter($value, fn ($v) => is_string($v) && str_starts_with($v, 'frg_'))) === count($value)) {
                return array_values(array_diff(array_unique($value), $drop));
            }

            return $value;
        };

        return $dedupe($out);
    }
}
