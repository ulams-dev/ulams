<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Ulams\CourseBuilder\Apply\BlueprintApplier;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Exceptions\BuilderException;
use Ulams\CourseBuilder\Jobs\RunJob;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\LivingCourse\Diff\ImpactAnalyzer;
use Ulams\LivingCourse\Events\ProposalApplied;
use Ulams\LivingCourse\Exceptions\ProposalException;
use Ulams\LivingCourse\Models\FragmentChange;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Models\RevisionFragment;

/**
 * Applies the accepted items of a proposal (plan 8.3): builds a new blueprint version of kind
 * `update` from the session's current version, writes it through the Course Builder applier (domain
 * services only, learner progress rules in ADR 0033), promotes the source revision and settles
 * staleness. Items whose element changed since the analysis are conflicts and are never applied.
 */
final class ApplyService
{
    public const HANDLER = 'living-course.apply';

    public function __construct(
        private readonly BlueprintApplier $applier,
        private readonly VersionService $versions,
        private readonly RevisionService $revisions,
        private readonly StalenessService $staleness,
        private readonly AuditLog $audit,
        private readonly EventLog $events,
        private readonly ImpactAnalyzer $impact,
    ) {
    }

    /**
     * Marks conflicts and returns what would be applied, without writing the course.
     *
     * @return array{conflicts:ProposalItem[],drift:array<string,string>,document:?array,current:?Version}
     */
    public function preview(Proposal $proposal): array
    {
        $session = Session::query()->findOrFail($proposal->session_id);
        $current = $session->currentVersion;
        if ($current === null || $current->status !== Version::APPROVED) {
            throw new ProposalException('The course has no approved version to update.', 409);
        }
        $elements = $this->impact->elements($current->document);
        $conflicts = [];
        $accepted = [];
        foreach (ProposalItem::query()->where('proposal_id', $proposal->id)->whereIn('kind', ['update', 'remove', 'citation_remap'])->whereIn('status', ['accepted', 'conflict', 'stale'])->get() as $item) {
            $el = $elements[$item->element_id] ?? null;
            if ($item->status === 'stale' || $el === null || Blueprint::fingerprint(self::snapshot($el['type'], $el['node'])) !== Blueprint::fingerprint($item->before)) {
                $item->forceFill(['status' => 'conflict'])->save();
                $conflicts[] = $item;
                continue;
            }
            if ($item->status === 'conflict') {
                $item->forceFill(['status' => 'accepted'])->save();
            }
            $accepted[] = $item;
        }
        $document = $conflicts === [] ? $this->build($proposal, $current->document, $accepted) : null;
        $drift = $document !== null ? $this->applier->drift($session, $document) : [];

        return ['conflicts' => $conflicts, 'drift' => $drift, 'document' => $document, 'current' => $current];
    }

    /** Creates the apply run; the caller checked conflicts with `preview()`. */
    public function start(Proposal $proposal, int $userId, bool $overwrite): Run
    {
        $proposal->forceFill(['status' => 'applying', 'decided_by' => $userId, 'decided_at' => now(), 'error' => null])->save();
        $run = Run::query()->create([
            'session_id' => $proposal->session_id, 'kind' => 'apply', 'status' => 'queued', 'user_id' => $userId,
            'input' => ['handler' => self::HANDLER, 'proposalId' => $proposal->id, 'overwrite' => $overwrite],
        ]);
        $proposal->forceFill(['run_id' => $run->id])->save();
        RunJob::dispatchFor($run->id);

        return $run;
    }

    /** Run handler. */
    public function execute(Run $run, Session $session): void
    {
        $proposal = Proposal::query()->findOrFail($run->input['proposalId']);
        try {
            $this->applyAccepted($proposal, $session, $run);
        } catch (\Throwable $e) {
            $proposal->forceFill(['status' => 'ready', 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
            throw $e;
        }
    }

    private function applyAccepted(Proposal $proposal, Session $session, Run $run): void
    {
        $preview = $this->preview($proposal);
        if ($preview['conflicts'] !== []) {
            throw new BuilderException('Some elements changed after the analysis. Ask for a new version of them or reject them, then apply again.', 409);
        }
        $author = $session->author;
        if (!$author instanceof Authenticatable) {
            throw new BuilderException('The session author no longer exists.', 409);
        }
        $userId = (int) $run->user_id;
        $current = $preview['current'];
        $doc = $preview['document'];
        $to = Revision::query()->findOrFail($proposal->to_revision_id);
        $from = Revision::query()->findOrFail($proposal->from_revision_id);

        $known = array_fill_keys(RevisionFragment::query()->whereIn('revision_id', Revision::query()->where('source_id', $proposal->source_id)->select('id'))->distinct()->pluck('fragment_id')->all(), true);
        $errors = array_values(array_filter(Checks::blueprint($doc, $known), fn ($e) => !str_starts_with($e, 'warning: ')));
        if ($errors !== []) {
            throw new BuilderException('The updated course would not pass its checks: ' . implode('; ', array_slice($errors, 0, 3)), 422);
        }

        $items = ProposalItem::query()->where('proposal_id', $proposal->id)->get();
        $applied = $items->where('status', 'accepted')->whereIn('kind', ['update', 'remove', 'citation_remap']);
        $callIds = array_values(array_unique(array_merge(...$applied->map(fn ($i) => (array) $i->ai_call_ids)->all() ?: [[]])));

        DB::transaction(function () use ($proposal, $session, $current, $doc, $to, $from, $author, $userId, $callIds, $items, $applied, $run) {
            $reason = sprintf('Source update r%d → r%d: %d change%s', $from->number, $to->number, $applied->count(), $applied->count() === 1 ? '' : 's');
            $version = $this->versions->create($session, $doc, 'update', 'ai', Version::APPROVED, $current, $reason, null, $callIds, $userId, [$proposal->source_id => $to->id]);
            $this->versions->setCurrent($session, $version);
            // the new text becomes the live fragments first: lesson sources and labels are rendered from them
            $this->revisions->promote($to->refresh(), $userId);
            $courseId = $this->applier->apply($session, $version, $author, (bool) ($run->input['overwrite'] ?? false), $proposal->id);
            $session->forceFill(['course_id' => $courseId, 'applied_version_id' => $version->id])->save();
            $this->staleness->set($session, $applied->pluck('element_id')->all(), 'in_sync');
            $this->staleness->settleOpen($session, 'dismissed');
            $counts = (array) $proposal->counts;
            $counts['applied'] = $applied->count();
            $counts['rejected'] = $items->where('status', 'rejected')->count();
            $counts['undecided'] = $items->whereIn('status', ['pending', 'stale', 'conflict'])->whereIn('kind', ['update', 'remove', 'no_change', 'manual'])->count();
            $proposal->forceFill(['status' => 'applied', 'result_version_id' => $version->id, 'applied_at' => now(), 'counts' => $counts, 'error' => null])->save();
            $this->audit->record('proposal.applied', [
                'session_id' => $session->id, 'subject_type' => 'proposal', 'subject_id' => $proposal->id, 'source_id' => $proposal->source_id,
                'revision_id' => $to->id, 'origin_ref' => $to->origin_ref, 'actor_id' => $userId, 'version_from' => $current->number, 'version_to' => $version->number,
                'ai_call_ids' => $callIds,
                'data' => ['number' => $proposal->number, 'from' => $from->number, 'to' => $to->number, 'applied' => $counts['applied'], 'rejected' => $counts['rejected'], 'undecided' => $counts['undecided'],
                    'items' => $applied->map(fn ($i) => ['element' => $i->element_id, 'kind' => $i->kind, 'class' => $i->change_class, 'before' => Blueprint::fingerprint($i->before), 'after' => $i->after !== null ? Blueprint::fingerprint($i->after) : null])->values()->all()],
            ]);
            $this->events->text($session, $run, 'The update is applied to the course. Learners keep their progress; see the notices for what they will be told.');
            $this->events->custom($session, $run, 'update_applied', ['proposalId' => $proposal->id, 'versionId' => $version->id, 'versionNumber' => $version->number]);
        });
        event(new ProposalApplied($proposal->refresh()));
    }

    /**
     * @param ProposalItem[]|\Illuminate\Support\Collection<int,ProposalItem> $accepted
     * @return array<string,mixed> the new document
     */
    public function build(Proposal $proposal, array $doc, iterable $accepted): array
    {
        $remap = [];
        foreach (FragmentChange::query()->where('to_revision_id', $proposal->to_revision_id)->where('kind', 'moved')->get() as $c) {
            $remap[$c->old_fragment_id] = $c->new_fragment_id;
        }
        $removedFragments = FragmentChange::query()->where('to_revision_id', $proposal->to_revision_id)->where('kind', 'removed')->pluck('old_fragment_id')->all();
        $removals = [];
        foreach ($accepted as $item) {
            if ($item->kind === 'remove') {
                $removals[] = $item;
                continue;
            }
            $doc = $item->kind === 'citation_remap' ? $this->remapElement($doc, $item) : $this->replace($doc, $item);
        }
        foreach ($removals as $item) {
            $doc = $this->remove($doc, $item->element_id);
        }
        $doc = $this->dropEmptyContainers($doc);
        // every moved passage keeps its text under a new id: citations anywhere follow it
        if ($remap !== []) {
            $doc = ProposalService::remapCitations($doc, $remap);
        }
        $doc = $this->dropRemovedCitations($doc, $removedFragments);
        $to = Revision::query()->find($proposal->to_revision_id);
        foreach ($doc['sources'] ?? [] as $n => $source) {
            if (($source['id'] ?? null) === $proposal->source_id && $to !== null) {
                $doc['sources'][$n]['fragmentCount'] = $to->fragment_count;
            }
        }
        $doc = $this->flagGrounding($doc, $accepted);

        return $doc;
    }

    /** @return array<string,mixed> */
    private static function snapshot(string $type, array $node): array
    {
        return match ($type) {
            'lesson' => ['id' => $node['id'], 'title' => $node['title'], 'citations' => $node['citations'] ?? []],
            'course' => ['id' => $node['id'], 'title' => $node['title'], 'faq' => $node['faq'] ?? []],
            default => $node,
        };
    }

    private function replace(array $doc, ProposalItem $item): array
    {
        $after = (array) $item->after;
        if ($item->element_type === 'objective') {
            foreach ($doc['course']['objectives'] ?? [] as $n => $o) {
                if ($o['id'] === $item->element_id) {
                    $doc['course']['objectives'][$n] = array_merge($o, $after);

                    return $doc;
                }
            }
            foreach ($doc['modules'] as $m => $module) {
                foreach ($module['lessons'] as $l => $lesson) {
                    foreach ($lesson['objectives'] as $n => $o) {
                        if ($o['id'] === $item->element_id) {
                            $doc['modules'][$m]['lessons'][$l]['objectives'][$n] = array_merge($o, $after);

                            return $doc;
                        }
                    }
                }
            }

            return $doc;
        }
        $found = Blueprint::find($doc, $item->element_id);

        return $found === null ? $doc : Blueprint::setAt($doc, $found['path'], $after);
    }

    private function remapElement(array $doc, ProposalItem $item): array
    {
        $after = (array) $item->after;
        if ($item->element_type === 'lesson') {
            $found = Blueprint::find($doc, $item->element_id);
            if ($found !== null) {
                $doc = Blueprint::setAt($doc, [...$found['path'], 'citations'], $after['citations'] ?? []);
            }

            return $doc;
        }
        if ($item->element_type === 'course') {
            $doc['course']['faq'] = $after['faq'] ?? [];

            return $doc;
        }

        return $this->replace($doc, $item);
    }

    private function remove(array $doc, string $elementId): array
    {
        $found = Blueprint::find($doc, $elementId);
        if ($found === null || !in_array($found['type'], ['block', 'question'], true)) {
            return $doc;
        }
        $path = $found['path'];
        $index = array_pop($path);
        $list = $path === [] ? $doc : $this->get($doc, $path);
        unset($list[$index]);

        return Blueprint::setAt($doc, $path, array_values($list));
    }

    /** @param array<int,string|int> $path */
    private function get(array $doc, array $path): mixed
    {
        foreach ($path as $key) {
            $doc = $doc[$key];
        }

        return $doc;
    }

    /** A lesson without blocks goes (with its quiz); a module without lessons goes; an empty quiz is dropped. */
    private function dropEmptyContainers(array $doc): array
    {
        foreach ($doc['modules'] as $m => $module) {
            foreach ($module['lessons'] as $l => $lesson) {
                if (is_array($lesson['quiz'] ?? null) && ($lesson['quiz']['questions'] ?? []) === []) {
                    $doc['modules'][$m]['lessons'][$l]['quiz'] = null;
                }
                if (($lesson['blocks'] ?? []) === [] && ($lesson['status'] ?? '') === 'generated') {
                    unset($doc['modules'][$m]['lessons'][$l]);
                }
            }
            $doc['modules'][$m]['lessons'] = array_values($doc['modules'][$m]['lessons']);
        }
        $doc['modules'] = array_values(array_filter($doc['modules'], fn ($m) => $m['lessons'] !== []));
        if (is_array($doc['finalTest'] ?? null) && ($doc['finalTest']['questions'] ?? []) === []) {
            $doc['finalTest'] = null;
        }
        if ($doc['modules'] === []) {
            throw new BuilderException('Applying this proposal would remove every lesson of the course. Reject the removals you do not want.', 422);
        }

        return $doc;
    }

    /** Removes ids of sections that no longer exist from citation lists that keep at least one other id. */
    private function dropRemovedCitations(array $doc, array $removed): array
    {
        if ($removed === []) {
            return $doc;
        }
        $drop = array_flip($removed);
        $walk = function (array $value) use (&$walk, $drop): array {
            foreach ($value as $key => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $isCitations = $key === 'citations' && array_is_list($item) && $item !== [] && count(array_filter($item, fn ($v) => is_string($v) && str_starts_with($v, 'frg_'))) === count($item);
                if ($isCitations) {
                    $kept = array_values(array_filter($item, fn ($id) => !isset($drop[$id])));
                    $value[$key] = $kept !== [] ? $kept : $item;
                } else {
                    $value[$key] = $walk($item);
                }
            }

            return $value;
        };

        return $walk($doc);
    }

    /** Unsupported-claim flags of accepted blocks become lesson flags (ADR 0028). */
    private function flagGrounding(array $doc, iterable $accepted): array
    {
        foreach ($accepted as $item) {
            $messages = (array) ($item->flags['grounding'] ?? []);
            if ($messages === [] || $item->element_type !== 'block') {
                continue;
            }
            $found = Blueprint::find($doc, $item->element_id);
            if ($found === null || $found['lessonId'] === null) {
                continue;
            }
            $lesson = Blueprint::find($doc, $found['lessonId']);
            $flags = array_values(array_unique([...($lesson['node']['flags'] ?? []), ...array_map(fn ($m) => mb_substr($m, 0, 500), $messages)]));
            $doc = Blueprint::setAt($doc, [...$lesson['path'], 'flags'], array_slice($flags, 0, 20));
        }

        return $doc;
    }
}
