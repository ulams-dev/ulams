<?php

namespace Ulams\LivingCourse\Support;

use Illuminate\Database\Eloquent\Builder;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Variant;
use Ulams\CourseBuilder\Models\Version;
use Ulams\LivingCourse\Models\Proposal;

/**
 * The branch of a builder session a proposal is for: the base course or one variant (ADR 0192). Everything that needs "the
 * course of this proposal", its live blueprint version or its entity map asks here, so a variant proposal never touches the base.
 */
final class ProposalBranch
{
    private function __construct(public readonly Session $session, public readonly ?Variant $variant)
    {
    }

    public static function of(Proposal $proposal): self
    {
        $session = Session::withTrashed()->findOrFail($proposal->session_id);
        $variant = $proposal->variant_id !== null ? Variant::query()->where('session_id', $session->id)->find($proposal->variant_id) : null;

        return new self($session, $variant);
    }

    public static function forVariant(Session $session, ?Variant $variant): self
    {
        return new self($session, $variant);
    }

    /** The branch of an LMS course, or null when the builder did not make it. */
    public static function ofCourse(int $courseId): ?self
    {
        $session = Session::withTrashed()->where('course_id', $courseId)->first();
        if ($session !== null) {
            return new self($session, null);
        }
        $variant = Variant::query()->where('course_id', $courseId)->first();

        return $variant !== null && $variant->session !== null ? new self($variant->session, $variant) : null;
    }

    public function courseId(): ?int
    {
        $id = $this->variant !== null ? $this->variant->course_id : $this->session->course_id;

        return $id !== null ? (int) $id : null;
    }

    /** The version learners have: what the branch's course was last applied from. */
    public function liveVersion(): ?Version
    {
        if ($this->variant === null) {
            return $this->session->currentVersion;
        }

        return $this->variant->applied_version_id !== null ? Version::query()->find($this->variant->applied_version_id) : null;
    }

    /** @return Builder<EntityMapEntry> */
    public function entries(): Builder
    {
        return EntityMapEntry::branch($this->session->id, $this->variant?->id);
    }
}
