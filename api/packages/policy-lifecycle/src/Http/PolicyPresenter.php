<?php

namespace Ulams\PolicyLifecycle\Http;

use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyVariant;
use Ulams\PolicyLifecycle\Models\PolicyVersion;
use Ulams\PolicyLifecycle\Services\VariantGenerator;

/** The array shape of a policy, its versions and its variants in every response. */
final class PolicyPresenter
{
    public static function version(PolicyVersion $version): array
    {
        return [
            'id' => $version->id,
            'number' => $version->number,
            'status' => $version->status,
            'changeClass' => $version->change_class,
            'summary' => $version->summary,
            'fileName' => $version->file_name,
            'fileSha256' => $version->file_sha256,
            'fileSize' => $version->file_size,
            'submittedBy' => $version->submitted_by,
            'approvedBy' => $version->approved_by,
            'approvedAt' => $version->approved_at?->toIso8601String(),
            'effectiveAt' => $version->effective_at?->toIso8601String(),
            'suggestedClass' => $version->suggested_class,
            'classReason' => $version->class_reason,
            'rejectedBy' => $version->rejected_by,
            'rejectReason' => $version->reject_reason,
            'rolledOutAt' => $version->rolled_out_at?->toIso8601String(),
            'createdAt' => $version->created_at?->toIso8601String(),
        ];
    }

    public static function variant(PolicyVariant $variant, VariantGenerator $generator): array
    {
        $session = $variant->session_id;

        return [
            'id' => $variant->id,
            'department' => $variant->department_label,
            'groupId' => $variant->group_id,
            'brief' => $variant->brief,
            'stage' => $generator->stage($variant),
            'sessionId' => $session,
            'courseId' => $variant->course_id,
            'pathId' => $variant->path_id,
            'examCourseId' => $variant->exam_course_id,
            'ruleId' => $variant->rule_id,
            'position' => $variant->position,
        ];
    }

    public static function policy(Policy $policy, VariantGenerator $generator, bool $detail = false): array
    {
        $policy->loadMissing(['versions', 'variants']);
        $latest = $policy->versions->last();
        $pending = $policy->versions->first(fn (PolicyVersion $v) => $v->status === PolicyVersion::IN_REVIEW);
        $row = [
            'id' => $policy->id,
            'title' => $policy->title,
            'ownerUserId' => $policy->owner_user_id,
            'status' => $policy->status,
            'minorDueDays' => $policy->minor_due_days,
            'majorDueDays' => $policy->major_due_days,
            'brief' => $policy->brief,
            'currentVersion' => $policy->current_version_id !== null ? $policy->versions->firstWhere('id', $policy->current_version_id)?->number : null,
            'latestVersion' => $latest?->number,
            'pendingReview' => $pending?->number,
            'departments' => $policy->variants->count(),
            'createdAt' => $policy->created_at?->toIso8601String(),
        ];
        if ($detail) {
            $row['versions'] = $policy->versions->map(fn (PolicyVersion $v) => self::version($v))->values()->all();
            $row['variants'] = $policy->variants->map(fn (PolicyVariant $v) => self::variant($v, $generator))->values()->all();
        }

        return $row;
    }
}
