<?php

namespace Ulams\LivingCourse\Services;

use Illuminate\Support\Facades\DB;
use Ulams\Ai\Contracts\LlmClient;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\StyleProfile;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Pipeline\PromptContext;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\StyleProfile\StyleCheck;
use Ulams\LivingCourse\Analysis\RestyleRequest;
use Ulams\LivingCourse\Diff\ImpactAnalyzer;
use Ulams\LivingCourse\Exceptions\ProposalException;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;

/**
 * Restyle proposals (ADR 0196): after a style profile changes, an author may ask for a proposal that brings the elements
 * of an applied course in line with it. Optional and never automatic. It has no source revision (like an improvement from
 * learner insights): one item per element the deterministic style check flags for a rule a rewrite can fix (banned phrase,
 * term to avoid, product-name casing), analysed by one model call per lesson, reviewed as a diff, and nothing reaches
 * the course before the author accepts and applies it. Rules a rewrite cannot fix from the text (reading level, landing
 * components, images) are not part of it, and neither is a quiz question whose correct option the style check flags: the
 * answer key is not restyled, the author edits that question by hand.
 *
 * Refused here, whoever calls: a profile that is not approved, AI switched off, a course with no approved version, a
 * course the check finds nothing in, and a fourth proposal of a session in one day. An open restyle of the session is
 * returned instead of a second one.
 */
final class StyleProposalService
{
    /** Element types a restyle may rewrite (a lesson's blocks and quiz questions). */
    public const TYPES = ['block', 'question'];

    /** Style check rules a rewrite can fix. */
    public const RULES = ['banned', 'terminology', 'casing'];

    public function __construct(
        private readonly AuditLog $audit,
        private readonly AnalysisService $analysis,
        private readonly PromptContext $context,
        private readonly LlmClient $llm,
        private readonly StyleCheck $check,
    ) {
    }

    public function assertAiEnabled(): void
    {
        if (!$this->llm->enabled()) {
            throw new ProposalException('AI is disabled on this installation. Edit the flagged elements by hand in the workspace.', 503, ['code' => 'ai_disabled']);
        }
    }

    /** @return array{estimateMicroUsd:int,elements:int,findings:int,skipped:int,answerKeys:int} */
    public function estimate(Session $session, StyleProfile $profile): array
    {
        $plan = $this->plan($session, $profile);
        $current = $this->currentVersion($session);
        $total = 0;
        $request = new RestyleRequest($current, $session, $this->context, $plan['byElement'], null);
        foreach ($this->byGroup($plan['items']) as $group => $specs) {
            $items = array_map(fn (array $spec) => new ProposalItem($spec + ['id' => $spec['element_id']]), $specs);
            $total += $this->analysis->estimateRequest($request, 'style_restyle', 'update-restyle', $items, (string) $group);
        }

        return ['estimateMicroUsd' => $total, 'elements' => count($plan['items']), 'findings' => $plan['findings'], 'skipped' => $plan['skipped'], 'answerKeys' => $plan['answerKeys']];
    }

    /** @return array{proposal:Proposal,state:string} state: created | kept_existing */
    public function create(Session $session, StyleProfile $profile, int $userId): array
    {
        $open = $this->openFor($session);
        if ($open !== null) {
            return ['proposal' => $open, 'state' => 'kept_existing'];
        }
        $plan = $this->plan($session, $profile);
        $current = $this->currentVersion($session);
        $limit = (int) config('living_course.cost.style_proposals_per_session_per_day', 3);
        $today = Proposal::query()->where('session_id', $session->id)->where('kind', Proposal::KIND_STYLE)->where('created_at', '>=', now()->startOfDay())->count();
        if ($today >= $limit) {
            throw new ProposalException(sprintf('This course already has %d restyle proposals today. Review them, or try again tomorrow.', $limit), 429, ['code' => 'daily_limit']);
        }

        $proposal = DB::transaction(function () use ($session, $current, $plan, $profile, $userId) {
            Session::query()->whereKey($session->id)->lockForUpdate()->first(['id']);
            $number = (int) Proposal::query()->where('session_id', $session->id)->max('number') + 1;
            $counts = [
                'items' => count($plan['items']), 'elements' => count($plan['items']), 'remaps' => 0, 'uncovered' => 0, 'major' => 0, 'answerChecks' => 0,
                'groups' => count($this->byGroup($plan['items'])), 'byType' => array_count_values(array_column($plan['items'], 'element_type')),
                'styleProfileId' => $profile->id, 'styleProfileName' => $profile->name, 'targetLabel' => $profile->name, 'findings' => $plan['findings'], 'skipped' => $plan['skipped'], 'answerKeys' => $plan['answerKeys'],
            ];
            $proposal = Proposal::query()->create([
                'number' => $number,
                'kind' => Proposal::KIND_STYLE,
                'session_id' => $session->id,
                'base_version_id' => $current->id,
                'status' => 'awaiting_analysis',
                'trigger' => 'style',
                'created_by' => $userId,
                'counts' => $counts,
                'evidence' => ['profileId' => $profile->id, 'findings' => $plan['byElement']],
            ]);
            foreach ($plan['items'] as $spec) {
                ProposalItem::query()->create($spec + ['proposal_id' => $proposal->id]);
            }
            $this->audit->record('proposal.created', [
                'session_id' => $session->id, 'subject_type' => 'proposal', 'subject_id' => $proposal->id, 'version_from' => $current->number, 'actor_id' => $userId,
                'data' => ['number' => $number, 'kind' => Proposal::KIND_STYLE, 'status' => $proposal->status, 'styleProfileId' => $profile->id, 'styleProfile' => $profile->name, 'elements' => count($plan['items']), 'findings' => $plan['findings']],
            ]);

            return $proposal;
        });

        return ['proposal' => $proposal, 'state' => 'created'];
    }

    /** The undecided restyle of a session, if any: one at a time. */
    public function openFor(Session $session): ?Proposal
    {
        return Proposal::query()->where('session_id', $session->id)->where('kind', Proposal::KIND_STYLE)->whereIn('status', Proposal::OPEN)->orderBy('number')->first();
    }

    private function currentVersion(Session $session): Version
    {
        $current = $session->currentVersion;
        if ($current === null || $current->status !== Version::APPROVED || !in_array($current->kind, VersionService::CONTENT_KINDS, true)) {
            throw new ProposalException('This course has no approved version yet, so there is nothing to restyle.', 409, ['code' => 'no_course']);
        }

        return $current;
    }

    /**
     * The elements a restyle would rewrite and why.
     *
     * @return array{items:array<int,array<string,mixed>>,byElement:array<string,array<int,array<string,mixed>>>,findings:int,skipped:int,answerKeys:int}
     */
    private function plan(Session $session, StyleProfile $profile): array
    {
        if ($profile->status !== StyleProfile::APPROVED) {
            throw new ProposalException('Only an approved style profile can restyle a course: ask an admin to approve it first.', 422, ['code' => 'profile_not_approved']);
        }
        $current = $this->currentVersion($session);
        $report = $this->check->run($current->document, $profile, (string) ($session->brief['language'] ?? 'en'));
        $found = array_values(array_filter([...$report['blocking'], ...$report['warnings']], fn (array $f) => in_array($f['rule'], self::RULES, true)));
        if ($found === []) {
            throw new ProposalException('Nothing in this course departs from the style profile in a way a rewrite can fix, so there is nothing to restyle.', 422, ['code' => 'nothing_to_restyle']);
        }
        $elements = (new ImpactAnalyzer())->elements($current->document);
        $live = $this->context->fragmentMap($session);
        $byElement = [];
        $skipped = [];
        $answerKey = [];
        $uncited = [];
        foreach ($found as $finding) {
            $id = (string) ($finding['elementId'] ?? '');
            $el = $elements[$id] ?? null;
            if ($el === null || !in_array($el['type'], self::TYPES, true)) {
                $skipped[$id] = true;
                continue;
            }
            // the answer key is never restyled: a finding inside a correct option is for the author to fix by hand
            if ($el['type'] === 'question' && self::inCorrectOption($el['node'], (string) ($finding['term'] ?? ''), (string) $finding['rule'])) {
                $skipped[$id] = true;
                $answerKey[$id] = true;
                continue;
            }
            $byElement[$id][] = array_intersect_key($finding, array_flip(['code', 'rule', 'severity', 'message', 'term', 'use', 'quote', 'citations']));
        }
        foreach (array_keys($answerKey) as $id) {
            // one finding in the answer key keeps the whole question out: its other findings would leave it half restyled
            unset($byElement[$id]);
        }
        $items = [];
        foreach ($elements as $id => $el) {
            if (!isset($byElement[$id])) {
                continue;
            }
            $cited = array_values(array_filter(RestyleRequest::citedIds($elements, $id, $el['group']), fn ($f) => isset($live[$f])));
            if ($cited === []) {
                // without a passage to cite, a rewrite could not be grounded: the author edits it by hand
                $skipped[$id] = true;
                $uncited[$id] = true;
                unset($byElement[$id]);
                continue;
            }
            $items[] = [
                'group_key' => $el['group'], 'element_id' => $id, 'element_type' => $el['type'], 'label' => $el['label'], 'kind' => 'manual',
                'fragment_ids' => $cited, 'severity' => 'minor', 'before' => $el['node'], 'status' => 'pending', 'answer_check' => false,
                'reason' => mb_substr((string) $byElement[$id][0]['message'], 0, 240),
            ];
        }
        if ($items === [] && $uncited === []) {
            throw new ProposalException('What departs from the style profile is in the answer keys of quiz questions, which a restyle never rewrites. Edit those questions by hand in the workspace.', 422, ['code' => 'nothing_to_restyle']);
        }
        if ($items === []) {
            throw new ProposalException('The elements that depart from the style profile cite no passage of the source material, so a rewrite could not be grounded in it. Edit them by hand in the workspace.', 422, ['code' => 'no_sources']);
        }

        return ['items' => $items, 'byElement' => $byElement, 'findings' => array_sum(array_map('count', $byElement)), 'skipped' => count($skipped), 'answerKeys' => count($answerKey)];
    }

    /** Whether a term of a finding is written in one of the correct options of a question. */
    private static function inCorrectOption(array $question, string $term, string $rule): bool
    {
        if ($term === '') {
            return false;
        }
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($term, '/') . '(?![\p{L}\p{N}])/u' . ($rule === 'casing' ? '' : 'i');
        foreach ((array) ($question['options'] ?? []) as $option) {
            if (!empty($option['correct']) && preg_match($pattern, (string) ($option['text'] ?? '')) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function byGroup(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $groups[$item['group_key']][] = $item;
        }

        return $groups;
    }
}
