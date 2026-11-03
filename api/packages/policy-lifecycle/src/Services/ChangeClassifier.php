<?php

namespace Ulams\PolicyLifecycle\Services;

use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\PolicyLifecycle\Exceptions\PolicyException;
use Ulams\PolicyLifecycle\Models\PolicyRollout;
use Ulams\PolicyLifecycle\Models\PolicyVersion;

/**
 * Suggests whether a policy change is minor (people sit an exam on the changed text) or major (people retake the
 * course), from what the Living Course proposals say changed (ADR 0140). It is a suggestion: the owner may raise the
 * class, and may lower it only with a recorded reason. Nothing here is generated or decided by a model.
 *
 * Major when any item that is not rejected changes the meaning of a lesson (change class major), changes a quiz answer,
 * adds a lesson, or removes a question; otherwise minor.
 */
final class ChangeClassifier
{
    public const RANK = [PolicyVersion::MINOR => 1, PolicyVersion::MAJOR => 2];

    /** @return array{class:string,reasons:array<int,string>} */
    public function suggest(PolicyVersion $version): array
    {
        $reasons = [];
        $proposals = PolicyRollout::query()->where('policy_version_id', $version->id)->whereNotNull('proposal_id')->pluck('proposal_id');
        foreach (ProposalItem::query()->whereIn('proposal_id', $proposals)->where('status', '!=', 'rejected')->orderBy('proposal_id')->orderBy('id')->get() as $item) {
            $reason = self::majorReason($item);
            if ($reason !== null) {
                $reasons[$reason] = true;
            }
        }
        $reasons = array_keys($reasons);

        return ['class' => $reasons === [] ? PolicyVersion::MINOR : PolicyVersion::MAJOR, 'reasons' => array_slice($reasons, 0, 8)];
    }

    private static function majorReason(ProposalItem $item): ?string
    {
        $label = (string) ($item->label ?: $item->element_id);

        return match (true) {
            $item->change_class === 'answer_changed' => "The correct answer of a question changes: {$label}",
            $item->kind === 'new_lesson' => "A new lesson is added: {$label}",
            $item->kind === 'remove' && $item->element_type === 'question' => "A question is removed: {$label}",
            $item->kind === 'update' && $item->change_class === 'major' => "A substantial change: {$label}",
            default => null,
        };
    }

    /**
     * The class the owner chose against the suggestion: none given means the suggestion; higher is always allowed;
     * lower needs a reason, which is recorded with the decision.
     *
     * @return array{class:string,reason:?string}
     *
     * @throws PolicyException
     */
    public function resolve(string $suggested, ?string $requested, ?string $reason): array
    {
        $reason = $reason !== null ? trim($reason) : null;
        if ($requested === null || $requested === '') {
            return ['class' => $suggested, 'reason' => $reason !== '' ? $reason : null];
        }
        if (!isset(self::RANK[$requested])) {
            throw new PolicyException('The change class is minor or major.');
        }
        if (self::RANK[$requested] < self::RANK[$suggested] && ($reason === null || $reason === '')) {
            throw new PolicyException(sprintf('The changes suggest a %s change. To approve it as %s, say why.', $suggested, $requested));
        }

        return ['class' => $requested, 'reason' => $reason !== '' ? $reason : null];
    }

    /** Open items the owner still has to decide before a version can be approved, per proposal. */
    public function undecided(Proposal $proposal): int
    {
        return ProposalItem::query()->where('proposal_id', $proposal->id)->where('status', 'pending')
            ->whereIn('kind', ['update', 'no_change', 'remove', 'new_lesson', 'manual'])->count();
    }
}
