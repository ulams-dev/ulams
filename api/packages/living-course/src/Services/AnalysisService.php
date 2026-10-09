<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Support\Facades\DB;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Dto\ContentBlock;
use Ulams\Ai\Dto\LlmRequest;
use Ulams\Ai\Dto\LlmResult;
use Ulams\Ai\Dto\Usage;
use Ulams\Ai\Exceptions\LlmException;
use Ulams\Ai\Models\AiCall;
use Ulams\Ai\Prompts\PromptRegistry;
use Ulams\Ai\Services\CostCalculator;
use Ulams\CourseBuilder\Blueprint\SchemaRegistry;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Ingestion\Fragmenter;
use Ulams\CourseBuilder\Jobs\RunJob;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Pipeline\Llm;
use Ulams\CourseBuilder\Pipeline\PatchService;
use Ulams\CourseBuilder\Pipeline\PromptContext;
use Ulams\CourseBuilder\Services\RunService;
use Ulams\LivingCourse\Analysis\UpdateRequest;
use Ulams\LivingCourse\Analysis\UpdateValidator;
use Ulams\LivingCourse\Jobs\AnalyseGroupJob;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;

/**
 * The AI part of an update proposal (plan 8.2, 8.3): one `update` call per lesson group, with a cost
 * estimate shown first, a cap per proposal and per source and month, a grounding check of the new
 * blocks and one run of kind `sync` whose steps (one per group) are resumable and retried alone.
 * The model proposes; items stay `pending` until the author decides.
 */
final class AnalysisService
{
    public const HANDLER = 'living-course.analyse';

    public function __construct(
        private readonly LlmClient $llm,
        private readonly PromptRegistry $prompts,
        private readonly PromptContext $context,
        private readonly SchemaRegistry $schemas,
        private readonly AuditLog $audit,
        private readonly StalenessService $staleness,
        private readonly EventLog $events,
    ) {
    }

    /** @return array<string,mixed> */
    public function schema(): array
    {
        return (array) json_decode((string) file_get_contents(__DIR__ . '/../../resources/schemas/outputs/update.json'), true);
    }

    private function model(): string
    {
        $profile = (string) config('ai.tasks.update.profile', 'default');

        return (string) config("ai.profiles.{$profile}.model", '');
    }

    /** Estimated cost in micro-USD of analysing every group of a proposal (cache reads after the first group). */
    public function estimate(Proposal $proposal): int
    {
        $session = Session::query()->findOrFail($proposal->session_id);
        $request = new UpdateRequest($proposal, $session, $this->context);
        $costs = CostCalculator::fromConfig();
        $system = Fragmenter::tokens($this->prompts->get('living-course', 'update')->text);
        $output = (int) config('living_course.cost.output_tokens_per_group', 3000);
        $total = 0;
        $first = true;
        foreach (array_slice($request->groupKeys(), 0, (int) config('living_course.cost.max_groups', 40)) as $key) {
            $built = $request->build($key, $request->items($key));
            $shared = Fragmenter::tokens($built['blocks'][0]->text) + Fragmenter::tokens($built['blocks'][1]->text);
            $own = $system + Fragmenter::tokens($built['blocks'][2]->text);
            $usage = $first ? new Usage($shared + $own, $output) : new Usage($own, $output, 0, 0, $shared);
            $total += $costs->cost($this->model(), $usage);
            $first = false;
        }

        return $total;
    }

    /**
     * Starts the analysis, or says why it cannot start.
     *
     * @return array{state:string,run:?Run,estimateMicroUsd:int,message:?string} state: started | needs_confirmation | budget_blocked | nothing_to_analyse
     */
    public function begin(Proposal $proposal, bool $confirmed, ?int $userId): array
    {
        $groups = (new UpdateRequest($proposal, Session::query()->findOrFail($proposal->session_id), $this->context))->groupKeys();
        if ($groups === []) {
            return ['state' => 'nothing_to_analyse', 'run' => null, 'estimateMicroUsd' => 0, 'message' => 'Nothing in this proposal needs an AI analysis.'];
        }
        $estimate = $this->estimate($proposal);
        $proposal->forceFill(['estimated_cost_micro_usd' => $estimate])->save();

        $cap = (int) round((float) config('living_course.cost.proposal_usd') * 1e6);
        $monthly = (int) round((float) config('living_course.cost.source_monthly_usd') * 1e6);
        $spent = (int) Proposal::query()->where('source_id', $proposal->source_id)->where('id', '!=', $proposal->id)->where('created_at', '>=', now()->startOfMonth())->sum('cost_micro_usd');
        if ($estimate + (int) $proposal->cost_micro_usd > $cap) {
            return $this->blocked($proposal, $estimate, sprintf('Analysing this proposal would cost about $%.2f, above the $%.2f limit per proposal. Update the changed elements by hand, or ask an admin to raise LIVING_COURSE_PROPOSAL_COST_USD.', $estimate / 1e6, $cap / 1e6));
        }
        if ($spent + $estimate > $monthly) {
            return $this->blocked($proposal, $estimate, sprintf('This source has used $%.2f of its $%.2f monthly AI budget for updates. Try next month, update by hand, or ask an admin to raise LIVING_COURSE_SOURCE_MONTHLY_USD.', $spent / 1e6, $monthly / 1e6));
        }
        $auto = (int) round((float) config('living_course.cost.auto_analyse_usd') * 1e6);
        if (!$confirmed && $estimate > $auto) {
            $proposal->forceFill(['status' => 'awaiting_analysis'])->save();

            return ['state' => 'needs_confirmation', 'run' => null, 'estimateMicroUsd' => $estimate, 'message' => sprintf('Analysing will cost about $%.2f. Confirm to start.', $estimate / 1e6)];
        }

        $proposal->forceFill(['status' => 'analysing', 'error' => null])->save();
        $run = Run::query()->create([
            'session_id' => $proposal->session_id, 'kind' => 'sync', 'status' => 'queued', 'user_id' => $userId,
            'input' => ['handler' => self::HANDLER, 'proposalId' => $proposal->id],
        ]);
        $proposal->forceFill(['run_id' => $run->id])->save();
        RunJob::dispatchFor($run->id);

        return ['state' => 'started', 'run' => $run, 'estimateMicroUsd' => $estimate, 'message' => null];
    }

    /** @return array{state:string,run:null,estimateMicroUsd:int,message:string} */
    private function blocked(Proposal $proposal, int $estimate, string $message): array
    {
        $proposal->forceFill(['status' => 'budget_blocked', 'error' => $message])->save();

        return ['state' => 'budget_blocked', 'run' => null, 'estimateMicroUsd' => $estimate, 'message' => $message];
    }

    /** Run handler: creates one step per group (up to the group cap) and dispatches the first window. */
    public function handleRun(Run $run, Session $session): bool
    {
        $proposal = Proposal::query()->findOrFail($run->input['proposalId']);
        $run->forceFill(['status' => 'running', 'stage' => 'groups', 'started_at' => now()])->save();
        $this->events->runStarted($run);
        $request = new UpdateRequest($proposal, $session, $this->context);
        $keys = $request->groupKeys();
        $max = (int) config('living_course.cost.max_groups', 40);
        foreach (array_slice($keys, 0, $max) as $key) {
            Step::query()->firstOrCreate(['run_id' => $run->id, 'key' => 'group:' . $key], ['stage' => 'groups', 'status' => 'pending']);
        }
        if (count($keys) > $max) {
            $proposal->forceFill(['counts' => array_merge((array) $proposal->counts, ['groupsLeft' => count($keys) - $max])])->save();
        }
        $this->events->text($session, $run, sprintf('Checking %d lesson group%s against the changed source.', min(count($keys), $max), min(count($keys), $max) === 1 ? '' : 's'));
        $this->progress($run, $proposal);
        $this->dispatchWindow($run);

        return false;
    }

    private function dispatchWindow(Run $run): void
    {
        $limit = max(1, (int) config('course_builder.limits.lesson_concurrency', 4));
        $running = Step::query()->where('run_id', $run->id)->whereIn('status', ['queued', 'running'])->count();
        foreach (Step::query()->where('run_id', $run->id)->where('status', 'pending')->orderBy('created_at')->orderBy('key')->limit(max(0, $limit - $running))->get() as $step) {
            if (Step::query()->whereKey($step->id)->where('status', 'pending')->update(['status' => 'queued'])) {
                AnalyseGroupJob::dispatchFor($step->id);
            }
        }
    }

    public function runStep(Step $step): void
    {
        $step->refresh();
        $run = $step->run;
        if ($step->status === 'done' || in_array($run->status, ['cancelled', 'failed', 'finished'], true)) {
            return;
        }
        $session = $run->session;
        $proposal = Proposal::query()->findOrFail($run->input['proposalId']);
        $step->forceFill(['status' => 'running', 'attempts' => $step->attempts + 1])->save();
        $this->progress($run, $proposal);
        try {
            $out = $this->analyseGroup($proposal, $session, substr($step->key, 6));
            $step->forceFill(['status' => 'done', 'output' => ['items' => $out['items']], 'error' => null, 'cost_micro_usd' => $step->cost_micro_usd + $out['cost']])->save();
        } catch (LlmException $e) {
            $step->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
            $run->forceFill(['status' => 'needs_attention'])->save();
            if ($e->reason === LlmException::BUDGET) {
                $proposal->forceFill(['status' => 'budget_blocked', 'error' => $e->getMessage()])->save();
            }
            $this->events->text($session, $run, 'Part of the analysis failed: ' . $e->getMessage() . ' You can retry that part; the rest of the proposal is not affected.');
        }
        $this->syncCost($proposal);
        $this->progress($run, $proposal->refresh());
        $this->dispatchWindow($run);
        $this->advance($run);
    }

    public function retry(Step $step): void
    {
        $run = $step->run;
        $step->forceFill(['status' => 'pending', 'error' => null])->save();
        $proposal = Proposal::query()->find($run->input['proposalId'] ?? null);
        if ($proposal !== null && $proposal->status === 'budget_blocked') {
            $proposal->forceFill(['status' => 'analysing', 'error' => null])->save();
        }
        if ($run->status === 'needs_attention' && !Step::query()->where('run_id', $run->id)->where('status', 'failed')->exists()) {
            $run->forceFill(['status' => 'running'])->save();
        }
        $this->dispatchWindow($run);
    }

    /** Finishes the run and settles the proposal once every step is done or failed. */
    public function advance(Run $run): void
    {
        $finished = DB::transaction(function () use ($run) {
            $locked = Run::query()->lockForUpdate()->find($run->id);
            if ($locked === null || in_array($locked->status, ['cancelled', 'failed', 'finished'], true)) {
                return false;
            }
            $steps = Step::query()->where('run_id', $locked->id)->get();
            if ($steps->contains(fn (Step $s) => in_array($s->status, ['pending', 'queued', 'running'], true))) {
                return false;
            }
            $failed = $steps->where('status', 'failed')->pluck('key')->map(fn ($k) => substr($k, 6))->all();
            $proposal = Proposal::query()->findOrFail($locked->input['proposalId']);
            $counts = (array) $proposal->counts;
            $counts['failedGroups'] = $failed;
            $analysed = ProposalItem::query()->where('proposal_id', $proposal->id)->whereIn('kind', ['update', 'no_change', 'remove'])->count();
            $counts['analysed'] = $analysed;
            $proposal->forceFill(['counts' => $counts, 'status' => $proposal->status === 'budget_blocked' ? 'budget_blocked' : 'ready'])->save();
            if ($failed === []) {
                $locked->forceFill(['status' => 'finished', 'finished_at' => now()])->save();
            } else {
                $locked->forceFill(['status' => 'needs_attention'])->save();
            }

            return $failed === [] ? $locked : false;
        });
        if ($finished instanceof Run) {
            $proposal = Proposal::query()->findOrFail($finished->input['proposalId']);
            $this->events->runFinished($finished);
            $this->audit->record('proposal.analysed', [
                'session_id' => $proposal->session_id, 'subject_type' => 'proposal', 'subject_id' => $proposal->id, 'source_id' => $proposal->source_id,
                'revision_id' => $proposal->to_revision_id, 'ai_call_ids' => $this->callIds($proposal), 'actor_type' => 'system',
                'data' => ['number' => $proposal->number, 'costMicroUsd' => $proposal->cost_micro_usd, 'analysed' => $proposal->counts['analysed'] ?? 0],
            ]);
            $this->events->text($finished->session, $finished, 'The update proposal is ready for your review. Nothing changes in the course until you approve it.');
        }
    }

    /** @return array{items:int,cost:int} */
    private function analyseGroup(Proposal $proposal, Session $session, string $groupKey): array
    {
        $request = new UpdateRequest($proposal, $session, $this->context);
        $items = array_values(array_filter($request->items($groupKey), fn (ProposalItem $i) => $i->kind === 'manual'));
        if ($items === []) {
            return ['items' => 0, 'cost' => 0];
        }

        return $this->analyseItems($proposal, $request, $groupKey, $items, null);
    }

    /**
     * One call for a set of items of a group, a grounding check of the new blocks (one regeneration
     * on unsupported claims, then a flag) and the write of the results.
     *
     * @param ProposalItem[] $items
     * @return array{items:int,cost:int}
     */
    public function analyseItems(Proposal $proposal, UpdateRequest $request, string $groupKey, array $items, ?string $authorRequest): array
    {
        $cost = 0;
        $built = $request->build($groupKey, $items, $authorRequest);
        $result = $this->generate($proposal, $built);
        $cost += $result->costMicroUsd;
        $callIds = $result->callIds;

        $check = $this->grounding($proposal, $result->data, $built);
        $cost += $check['cost'];
        $callIds = [...$callIds, ...$check['callIds']];
        if ($check['unsupported'] !== []) {
            $redo = $request->build($groupKey, $items, $authorRequest, array_map(fn ($u) => ['elementId' => $u['elementId'], 'claim' => $u['claim'], 'reason' => $u['reason']], $check['unsupported']));
            $second = $this->generate($proposal, $redo);
            $cost += $second->costMicroUsd;
            $callIds = [...$callIds, ...$second->callIds];
            $result = $second;
            $built = $redo;
            $check = $this->grounding($proposal, $result->data, $built);
            $cost += $check['cost'];
            $callIds = [...$callIds, ...$check['callIds']];
        }
        $written = $this->store($proposal, $items, $result->data, $built, $callIds, $check['unsupported']);

        return ['items' => $written, 'cost' => $cost];
    }

    /** @param array{blocks:ContentBlock[],expected:array,known:array<string,string>,objectiveIds:string[]} $built */
    private function generate(Proposal $proposal, array $built): LlmResult
    {
        $userId = $proposal->decided_by ?? $proposal->created_by ?? Session::query()->whereKey($proposal->session_id)->value('author_id');

        return $this->llm->generate(new LlmRequest(
            task: 'update',
            prompt: $this->prompts->get('living-course', 'update'),
            blocks: $built['blocks'],
            schema: $this->schema(),
            subject: $proposal->subject(),
            userId: $userId !== null ? (int) $userId : null,
            validator: fn (array $data) => UpdateValidator::validate($data, $built['expected'], $built['known'], $built['objectiveIds']),
            budget: ['cost_micro_usd' => (int) round((float) config('living_course.cost.proposal_usd') * 1e6)],
        ));
    }

    /**
     * The Phase 2 grounding check on the blocks the model wrote.
     *
     * @return array{unsupported:array<int,array{elementId:string,claim:string,reason:string}>,cost:int,callIds:string[]}
     */
    private function grounding(Proposal $proposal, array $data, array $built): array
    {
        $blocks = [];
        foreach ($data['items'] ?? [] as $item) {
            if (($item['decision'] ?? '') === 'update' && is_array($item['block'] ?? null)) {
                $blocks[] = ['elementId' => $item['elementId'], 'markdown' => $item['block']['markdown'], 'citations' => $item['block']['citations']];
            }
        }
        if ($blocks === []) {
            return ['unsupported' => [], 'cost' => 0, 'callIds' => []];
        }
        $mini = "<source_document untrusted=\"true\">\n";
        $cited = [];
        foreach ($blocks as $b) {
            foreach ($b['citations'] as $id) {
                $cited[$id] = true;
            }
        }
        foreach (array_keys($cited) as $id) {
            if (isset($built['known'][$id])) {
                $mini .= '<fragment id="' . $id . "\">\n" . PromptContext::esc($built['known'][$id]) . "\n</fragment>\n";
            }
        }
        $mini .= '</source_document>';
        $result = $this->llm->generate(new LlmRequest(
            task: 'grounding',
            prompt: $this->prompts->get(Llm::PROMPTS, 'grounding'),
            blocks: [ContentBlock::text($mini), $this->context->instruction('Check each block against its cited fragments.', ['blocks' => array_map(fn ($b, $i) => ['index' => $i, 'markdown' => $b['markdown'], 'citations' => $b['citations']], $blocks, array_keys($blocks))])],
            schema: $this->schemas->get('outputs/grounding'),
            subject: $proposal->subject(),
            validator: fn (array $d) => array_values(array_filter(array_map(fn ($u) => ($u['blockIndex'] ?? -1) >= count($blocks) ? 'unsupported: blockIndex out of range' : null, $d['unsupported'] ?? []))),
            budget: ['cost_micro_usd' => (int) round((float) config('living_course.cost.proposal_usd') * 1e6)],
        ));
        $unsupported = array_map(fn ($u) => ['elementId' => $blocks[$u['blockIndex']]['elementId'], 'claim' => (string) $u['claim'], 'reason' => (string) $u['reason']], $result->data['unsupported'] ?? []);

        return ['unsupported' => $unsupported, 'cost' => $result->costMicroUsd, 'callIds' => $result->callIds];
    }

    /**
     * Writes the validated answer into the items (code decides the change class and the answer change).
     *
     * @param ProposalItem[] $items
     * @param array<int,array{elementId:string,claim:string,reason:string}> $unsupported
     */
    private function store(Proposal $proposal, array $items, array $data, array $built, array $callIds, array $unsupported): int
    {
        $byId = [];
        foreach ($data['items'] ?? [] as $o) {
            $byId[$o['elementId']] = $o;
        }
        $flagsFor = [];
        foreach ($unsupported as $u) {
            $flagsFor[$u['elementId']][] = mb_substr('Possibly unsupported: ' . $u['claim'] . ' (' . $u['reason'] . ')', 0, 500);
        }
        $written = 0;
        foreach ($items as $item) {
            $out = $byId[$item->element_id] ?? null;
            if ($out === null) {
                continue;
            }
            $type = $built['expected'][$item->element_id]['type'];
            $before = $built['expected'][$item->element_id]['node'];
            $after = match (true) {
                $out['decision'] !== 'update' => null,
                $type === 'block' => PatchService::merge('block', $before, $out['block']),
                $type === 'question' => PatchService::merge('question', $before, $out['question']),
                default => array_merge($before, ['text' => trim($out['objective']['text']), 'citations' => array_values(array_unique($out['objective']['citations']))]),
            };
            $class = UpdateValidator::changeClass($out, $type, $before, $after);
            $flags = (array) $item->flags;
            unset($flags['grounding']);
            if (isset($flagsFor[$item->element_id])) {
                $flags['grounding'] = $flagsFor[$item->element_id];
            }
            if ($type === 'question' && $out['answerStatus'] === 'unsure') {
                $flags['checkAnswer'] = true;
            }
            $item->forceFill([
                'kind' => $out['decision'],
                'reason' => mb_substr((string) $out['reason'], 0, 240),
                'severity' => $out['severity'],
                'fragment_ids' => $out['fragmentIds'] !== [] ? $out['fragmentIds'] : $item->fragment_ids,
                'after' => $after,
                'change_class' => $class,
                'answer_status' => $type === 'question' ? ($class === 'answer_changed' ? 'changed' : ($out['answerStatus'] === 'changed' ? 'unchanged' : $out['answerStatus'])) : null,
                'flags' => $flags === [] ? null : $flags,
                'status' => 'pending',
                'ai_call_ids' => array_values(array_unique([...((array) $item->ai_call_ids), ...$callIds])),
            ])->save();
            $written++;
        }

        return $written;
    }

    public function syncCost(Proposal $proposal): void
    {
        $proposal->forceFill(['cost_micro_usd' => (int) AiCall::query()->forSubject(Proposal::SUBJECT_TYPE, $proposal->id)->sum('cost_micro_usd')])->save();
    }

    /** @return string[] */
    private function callIds(Proposal $proposal): array
    {
        return AiCall::query()->forSubject(Proposal::SUBJECT_TYPE, $proposal->id)->orderBy('id')->limit(500)->pluck('id')->all();
    }

    private function progress(Run $run, Proposal $proposal): void
    {
        $steps = Step::query()->where('run_id', $run->id)->get();
        $this->events->custom($run->session, $run, 'update_analysis', [
            'proposalId' => $proposal->id,
            'total' => $steps->count(),
            'done' => $steps->where('status', 'done')->count(),
            'failed' => $steps->where('status', 'failed')->count(),
            'costMicroUsd' => (int) $proposal->cost_micro_usd,
        ]);
    }
}
