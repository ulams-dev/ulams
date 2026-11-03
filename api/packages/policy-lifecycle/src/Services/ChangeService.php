<?php

namespace Ulams\PolicyLifecycle\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\CourseBuilder\Models\Source;
use Ulams\LivingCourse\Exceptions\ProposalException;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\Revision;
use Ulams\LivingCourse\Services\ApplyService;
use Ulams\LivingCourse\Services\DecisionService;
use Ulams\LivingCourse\Services\SourceSync;
use Ulams\PolicyLifecycle\Exceptions\PolicyException;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyCertificate;
use Ulams\PolicyLifecycle\Models\PolicyRollout;
use Ulams\PolicyLifecycle\Models\PolicyVariant;
use Ulams\PolicyLifecycle\Models\PolicyVersion;
use Ulams\TemplatesPdf\Models\FabricPDF;

/**
 * A policy change from upload to approval (ADR 0140, plan 4.3 steps 6 and 7). A new file becomes a new revision of each
 * department's source, so Living Course detects what changed, proposes the course updates (one proposal per
 * department, reviewed item by item as for any source change) and keeps the hash-chained audit. The owner reviews the
 * source diff and the proposals, then approves: every department course is updated, and only when all of them are does
 * the rollout (`RolloutService`) revoke and reassign. The class of the change is suggested from the proposals.
 */
final class ChangeService
{
    public function __construct(
        private readonly PolicyService $policies,
        private readonly SourceSync $sync,
        private readonly SourceIngestor $ingestor,
        private readonly ChangeClassifier $classifier,
        private readonly ChangeSummary $summaries,
        private readonly ApplyService $applying,
        private readonly DecisionService $decisions,
        private readonly RolloutCoordinator $coordinator,
        private readonly PolicyAudit $audit,
    ) {
    }

    /**
     * Submits a new file as the next version, `in_review`. Creates a revision and a proposal per department.
     *
     * @throws PolicyException the policy is not ready for a change, the file is the same text, or it is not a document
     * @throws \Ulams\Uploads\Exceptions\UploadRejected
     */
    public function submit(Policy $policy, UploadedFile $file, Authenticatable $actor): PolicyVersion
    {
        $current = $policy->current_version_id !== null ? PolicyVersion::query()->find($policy->current_version_id) : null;
        if ($current === null || $current->status !== PolicyVersion::APPROVED) {
            throw new PolicyException('Approve the first version before uploading a change.', 409);
        }
        if (PolicyVersion::query()->where('policy_id', $policy->id)->whereIn('status', [PolicyVersion::IN_REVIEW, PolicyVersion::APPLYING])->exists()) {
            throw new PolicyException('Another version is waiting for a decision. Approve or reject it first.', 409);
        }
        $variants = $policy->variants;
        foreach ($variants as $variant) {
            if ($variant->session_id === null || $variant->source_id === null || $variant->course_id === null) {
                throw new PolicyException("{$variant->department_label}: the department course is not set up, so the policy cannot change.", 409);
            }
        }
        $info = $this->ingestor->inspect($file);
        if ($info['sha256'] === $current->file_sha256) {
            throw new PolicyException('This file is the current version of the policy.', 409);
        }

        $number = (int) PolicyVersion::query()->where('policy_id', $policy->id)->max('number') + 1;
        $version = DB::transaction(fn () => $this->policies->storeVersion($policy, $number, $file, $info, PolicyVersion::MINOR, $actor));
        $rollouts = [];
        $revisions = [];
        try {
            foreach ($variants as $variant) {
                [$rollout, $revision] = $this->openRollout($version, $variant, (int) $actor->getAuthIdentifier());
                $rollouts[] = $rollout;
                if ($revision !== null) {
                    $revisions[] = $revision;
                }
            }
        } catch (\Throwable $e) {
            $this->discard($version);
            throw $e;
        }
        if ($revisions === [] && collect($rollouts)->every(fn (PolicyRollout $r) => $r->status === PolicyRollout::NO_CHANGE)) {
            $this->discard($version);
            throw new PolicyException('The text of this file is the same as the current version; nothing would change.', 409);
        }

        $version->forceFill(['summary' => $this->summaries->sentence($this->summaries->of($revisions[0] ?? null))])->save();
        $this->audit->record('policy.version.submitted', $policy, $version, [
            'sha256' => $version->file_sha256,
            'departments' => collect($rollouts)->mapWithKeys(fn (PolicyRollout $r) => [PolicyVariant::query()->find($r->variant_id)?->department_label => $r->status])->all(),
        ]);

        return $version->refresh();
    }

    /**
     * @return array{0:PolicyRollout,1:?Revision}
     */
    private function openRollout(PolicyVersion $version, PolicyVariant $variant, int $userId): array
    {
        $rollout = PolicyRollout::query()->create(['policy_version_id' => $version->id, 'variant_id' => $variant->id, 'status' => PolicyRollout::PENDING]);
        $source = Source::query()->findOrFail($variant->source_id);
        try {
            $result = $this->sync->upload($source, $this->policies->fileOf($version), $userId);
        } catch (RuntimeException $e) {
            $rollout->forceFill(['status' => PolicyRollout::FAILED, 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();

            return [$rollout, null];
        }
        $revision = $result['revision'];
        if ($result['unchanged']) {
            $rollout->forceFill(['status' => PolicyRollout::NO_CHANGE])->save();

            return [$rollout, null];
        }
        $proposal = Proposal::query()->where('to_revision_id', $revision->id)->orderByDesc('created_at')->first();
        if ($proposal === null && $revision->status === 'ingested') {
            // changed text that cites something, but no proposal was made: the daily limit of proposals per source held it
            $rollout->forceFill(['status' => PolicyRollout::FAILED, 'error' => 'No update proposal could be made for this department today (the daily limit per source). Try again tomorrow.'])->save();

            return [$rollout, $revision];
        }
        $rollout->forceFill(['proposal_id' => $proposal?->id])->save();
        $this->refresh($rollout);

        return [$rollout->refresh(), $revision];
    }

    /**
     * Brings the rollout's status in line with its proposal's. A proposal that is `ready` again after an apply was
     * started means the apply failed: the rollout shows `failed` with the proposal's error and can be approved again.
     */
    public function refresh(PolicyRollout $rollout): PolicyRollout
    {
        if (in_array($rollout->status, [PolicyRollout::APPLIED, PolicyRollout::NO_CHANGE], true) && $rollout->rolled_out_at !== null) {
            return $rollout;
        }
        $proposal = $rollout->proposal_id !== null ? Proposal::query()->find($rollout->proposal_id) : null;
        if ($proposal === null) {
            // no proposal: no course element cites a changed section (or the proposal could not be made: then it is already failed)
            if ($rollout->status !== PolicyRollout::FAILED) {
                $rollout->forceFill(['status' => PolicyRollout::NO_CHANGE])->save();
            }

            return $rollout;
        }
        $attributes = match ($proposal->status) {
            'no_impact' => ['status' => PolicyRollout::NO_CHANGE],
            'ready' => in_array($rollout->status, [PolicyRollout::APPLYING, PolicyRollout::FAILED], true) && $proposal->error !== null
                ? ['status' => PolicyRollout::FAILED, 'error' => mb_substr((string) $proposal->error, 0, 1000)]
                : ['status' => PolicyRollout::READY, 'error' => null],
            'applying' => ['status' => PolicyRollout::APPLYING],
            'applied' => ['status' => PolicyRollout::APPLIED],
            'rejected' => ['status' => PolicyRollout::REJECTED],
            default => ['status' => PolicyRollout::PENDING],
        };
        $rollout->forceFill($attributes)->save();

        return $rollout;
    }

    /** @return array<int,PolicyRollout> */
    public function rollouts(PolicyVersion $version): array
    {
        return PolicyRollout::query()->where('policy_version_id', $version->id)->orderBy('id')->get()->map(fn (PolicyRollout $r) => $this->refresh($r))->all();
    }

    /** A rejected submission (nothing changed, or a department could not take the file) leaves no version behind. */
    private function discard(PolicyVersion $version): void
    {
        PolicyRollout::query()->where('policy_version_id', $version->id)->delete();
        Storage::disk(SourceIngestor::disk())->delete($version->file_path);
        $version->delete();
    }

    /**
     * What stands between this version and its approval, per department (empty when it can be approved).
     *
     * @return array<int,string>
     */
    public function blockers(PolicyVersion $version): array
    {
        $problems = [];
        foreach ($this->rollouts($version) as $rollout) {
            $label = PolicyVariant::query()->find($rollout->variant_id)?->department_label ?? $rollout->variant_id;
            $proposal = $rollout->proposal_id !== null ? Proposal::query()->find($rollout->proposal_id) : null;
            if ($rollout->status === PolicyRollout::FAILED && $proposal === null) {
                $problems[] = "{$label}: " . ($rollout->error ?: 'the file could not be processed for this department.');
            } elseif ($rollout->status === PolicyRollout::PENDING) {
                $problems[] = "{$label}: the update proposal is still being prepared (status {$proposal?->status}). Wait for it, or finish it in the Studio.";
            } elseif ($rollout->status === PolicyRollout::REJECTED) {
                $problems[] = "{$label}: its update proposal was rejected. Reject this version, and upload a corrected file as a new one.";
            } elseif ($proposal !== null && $rollout->status === PolicyRollout::READY && ($open = $this->classifier->undecided($proposal)) > 0) {
                $problems[] = "{$label}: {$open} item" . ($open === 1 ? '' : 's') . ' of the update still wait for your decision.';
            }
        }

        return $problems;
    }

    /**
     * What approving will do, per department: how many live certificates it revokes and how many people it reassigns
     * (everyone whose certificate is linked to the current version of that department's course).
     *
     * @return array{changeClass:string,departments:array<int,array{id:string,department:string,affected:bool,certificatesToRevoke:int,peopleToReassign:int,assigns:string}>}
     */
    public function preflight(Policy $policy, PolicyVersion $version, string $class): array
    {
        $rows = [];
        foreach ($this->rollouts($version) as $rollout) {
            $variant = PolicyVariant::query()->find($rollout->variant_id);
            if ($variant === null) {
                continue;
            }
            $affected = $rollout->status !== PolicyRollout::NO_CHANGE;
            $live = $affected && $policy->current_version_id !== null
                ? FabricPDF::query()->whereIn('id', PolicyCertificate::query()->where('policy_version_id', $policy->current_version_id)->where('variant_id', $variant->id)->pluck('fabric_pdf_id'))
                    ->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->get(['id', 'user_id'])
                : collect();
            $rows[] = [
                'id' => $variant->id,
                'department' => $variant->department_label,
                'affected' => $affected,
                'certificatesToRevoke' => $live->count(),
                'peopleToReassign' => $live->pluck('user_id')->unique()->count(),
                'assigns' => $class === PolicyVersion::MAJOR ? 'course' : 'exam',
            ];
        }

        return ['changeClass' => $class, 'departments' => $rows];
    }

    /**
     * Approves a change: updates every department course, then (when all are updated) rolls out. Safe to call again
     * after a failure; departments already updated are not touched twice.
     *
     * @throws PolicyException
     */
    public function approve(Policy $policy, PolicyVersion $version, Authenticatable $actor, ?string $class, ?string $reason, bool $overwrite): PolicyVersion
    {
        if ($version->policy_id !== $policy->id) {
            throw new PolicyException('That version belongs to another policy.', 404);
        }
        if (!in_array($version->status, [PolicyVersion::IN_REVIEW, PolicyVersion::APPLYING], true)) {
            throw new PolicyException('This version is not waiting for a decision.', 409);
        }
        $problems = $this->blockers($version);
        if ($problems !== []) {
            throw new PolicyException("The change cannot be approved yet.\n" . implode("\n", $problems));
        }
        $suggestion = $this->classifier->suggest($version);
        $resolved = $this->classifier->resolve($suggestion['class'], $class, $reason);

        // a department whose earlier apply failed is tried again; one already updated is left alone
        $ready = array_values(array_filter($this->rollouts($version), fn (PolicyRollout $r) => $r->proposal_id !== null && in_array($r->status, [PolicyRollout::READY, PolicyRollout::FAILED], true)));
        $this->assertApplicable($ready, $overwrite);

        $first = $version->status === PolicyVersion::IN_REVIEW;
        DB::transaction(function () use ($policy, $version, $actor, $suggestion, $resolved, $first) {
            $version->forceFill([
                'status' => PolicyVersion::APPLYING,
                'change_class' => $resolved['class'],
                'suggested_class' => $suggestion['class'],
                'class_reason' => $resolved['reason'],
                'approved_by' => $actor->getAuthIdentifier(),
                'approved_at' => $version->approved_at ?? now(),
            ])->save();
            if ($first) {
                $this->audit->record('policy.version.approved', $policy, $version, [
                    'actor_for_owner' => (int) $actor->getAuthIdentifier() !== (int) $policy->owner_user_id,
                    'suggested_class' => $suggestion['class'],
                    'change_class' => $resolved['class'],
                    'class_reason' => $resolved['reason'],
                    'reasons' => $suggestion['reasons'],
                ]);
            }
        });

        foreach ($ready as $rollout) {
            $proposal = Proposal::query()->findOrFail($rollout->proposal_id);
            $rollout->forceFill(['status' => PolicyRollout::APPLYING, 'error' => null])->save();
            $this->applying->start($proposal, (int) $actor->getAuthIdentifier(), $overwrite);
        }
        $this->coordinator->maybeRun($version);

        return $version->refresh();
    }

    /**
     * Refuses to start when any department's update would hit a conflict (an element edited after the analysis) or
     * overwrite edits made in the admin without `overwrite`. Nothing has been applied yet at this point.
     *
     * @param array<int,PolicyRollout> $rollouts
     *
     * @throws PolicyException
     */
    private function assertApplicable(array $rollouts, bool $overwrite): void
    {
        $problems = [];
        foreach ($rollouts as $rollout) {
            $label = PolicyVariant::query()->find($rollout->variant_id)?->department_label ?? $rollout->variant_id;
            try {
                $preview = $this->applying->preview(Proposal::query()->findOrFail($rollout->proposal_id));
            } catch (ProposalException $e) {
                $problems[] = "{$label}: {$e->getMessage()}";
                continue;
            }
            if ($preview['conflicts'] !== []) {
                $problems[] = "{$label}: " . count($preview['conflicts']) . ' element(s) were edited after the analysis. Ask for a new version of them or reject them in the Studio.';
            } elseif ($preview['drift'] !== [] && !$overwrite) {
                $problems[] = "{$label}: these elements were edited in the admin after the last apply: " . implode(', ', array_slice(array_values($preview['drift']), 0, 5)) . '. Approve with overwrite to replace those edits.';
            }
        }
        if ($problems !== []) {
            throw new PolicyException("The change cannot be applied.\n" . implode("\n", $problems), 409);
        }
    }

    /**
     * Rejects a version that is still in review: its open proposals are rejected (the new file is recorded as seen), the
     * current version stays in force, and a corrected file is uploaded as a new version.
     *
     * @throws PolicyException
     */
    public function reject(Policy $policy, PolicyVersion $version, Authenticatable $actor, string $reason): PolicyVersion
    {
        if ($version->policy_id !== $policy->id) {
            throw new PolicyException('That version belongs to another policy.', 404);
        }
        if ($version->status !== PolicyVersion::IN_REVIEW || $version->change_class === PolicyVersion::INITIAL) {
            throw new PolicyException('Only a change that is still in review can be rejected.', 409);
        }
        DB::transaction(function () use ($policy, $version, $actor, $reason) {
            foreach ($this->rollouts($version) as $rollout) {
                $proposal = $rollout->proposal_id !== null ? Proposal::query()->find($rollout->proposal_id) : null;
                if ($proposal !== null && $proposal->isOpen()) {
                    $this->decisions->rejectProposal($proposal, (int) $actor->getAuthIdentifier());
                }
                $rollout->forceFill(['status' => PolicyRollout::REJECTED])->save();
            }
            $version->forceFill(['status' => PolicyVersion::REJECTED, 'rejected_by' => $actor->getAuthIdentifier(), 'reject_reason' => mb_substr($reason, 0, 2000)])->save();
            $this->audit->record('policy.version.rejected', $policy, $version, ['reason' => mb_substr($reason, 0, 500), 'actor_for_owner' => (int) $actor->getAuthIdentifier() !== (int) $policy->owner_user_id]);
        });

        return $version->refresh();
    }
}
