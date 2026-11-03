<?php

namespace Ulams\CourseVariants\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Ulams\Courses\Repositories\Contracts\CourseRepositoryContract;
use Ulams\CourseVariants\Models\VariantGroup;
use Ulams\CourseVariants\Models\VariantGroupMember;
use Ulams\CourseVariants\Services\Contracts\CourseVariantServiceContract;

/**
 * Families of variant courses (ADR 0192). The catalogue lists only courses that are `findable`, so a member course is
 * hidden by switching that flag off through the courses repository; the default course is never touched.
 */
final class CourseVariantService implements CourseVariantServiceContract
{
    public function __construct(private readonly CourseRepositoryContract $courses)
    {
    }

    public function family(int $defaultCourseId): VariantGroup
    {
        if (VariantGroupMember::query()->where('course_id', $defaultCourseId)->exists()) {
            throw new InvalidArgumentException('A variant course cannot be the default course of another family.');
        }

        return VariantGroup::query()->firstOrCreate(['default_course_id' => $defaultCourseId]);
    }

    public function addMember(int $defaultCourseId, int $courseId, string $variantKey, string $label, ?string $level, ?string $role, int $position = 0): VariantGroupMember
    {
        if ($courseId === $defaultCourseId) {
            throw new InvalidArgumentException('The default course is not a member of its own family.');
        }
        $member = DB::transaction(function () use ($defaultCourseId, $courseId, $variantKey, $label, $level, $role, $position) {
            $group = $this->family($defaultCourseId);
            $existing = VariantGroupMember::query()->where('course_id', $courseId)->first();
            if ($existing !== null && $existing->group_id !== $group->id) {
                throw new InvalidArgumentException('That course already belongs to another family.');
            }

            return VariantGroupMember::query()->updateOrCreate(
                ['course_id' => $courseId],
                ['group_id' => $group->id, 'variant_key' => $variantKey, 'label' => mb_substr($label, 0, 255), 'level' => $level, 'role' => $role, 'position' => $position],
            );
        });
        $this->courses->update(['findable' => false], $courseId);
        app(FamilyDirectory::class)->forget();

        return $member;
    }

    public function removeMember(int $courseId): void
    {
        $member = VariantGroupMember::query()->where('course_id', $courseId)->first();
        if ($member === null) {
            return;
        }
        $member->delete();
        $this->courses->update(['findable' => true], $courseId);
        app(FamilyDirectory::class)->forget();
    }

    public function setDefaultAudience(int $defaultCourseId, ?string $label, ?string $level, ?string $role): VariantGroup
    {
        $group = $this->family($defaultCourseId);
        $group->forceFill([
            'default_label' => $label !== null && trim($label) !== '' ? mb_substr(trim($label), 0, 255) : null,
            'default_level' => $level,
            'default_role' => $role !== null && trim($role) !== '' ? mb_substr(trim($role), 0, 64) : null,
        ])->save();
        app(FamilyDirectory::class)->forget();

        return $group;
    }

    public function retire(int $courseId): void
    {
        // through the model, so the cached course pages are cleared (a mass update would leave them stale)
        VariantGroupMember::query()->where('course_id', $courseId)->whereNull('retired_at')->get()->each(fn (VariantGroupMember $m) => $m->forceFill(['retired_at' => now()])->save());
        app(FamilyDirectory::class)->forget();
    }

    public function groupForCourse(int $courseId): ?VariantGroup
    {
        $member = VariantGroupMember::query()->where('course_id', $courseId)->first();

        return $member !== null ? VariantGroup::query()->find($member->group_id) : VariantGroup::query()->where('default_course_id', $courseId)->first();
    }

    public function describe(int $courseId): ?array
    {
        $group = $this->groupForCourse($courseId);
        if ($group === null) {
            return null;
        }

        return [
            'groupId' => $group->id,
            'defaultCourseId' => (int) $group->default_course_id,
            'isDefault' => (int) $group->default_course_id === $courseId,
            'members' => $group->members->map(fn (VariantGroupMember $m) => [
                'courseId' => (int) $m->course_id, 'variantKey' => $m->variant_key, 'label' => $m->label,
                'level' => $m->level, 'role' => $m->role, 'position' => (int) $m->position,
            ])->all(),
        ];
    }
}
