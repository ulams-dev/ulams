<?php

namespace Ulams\CourseVariants\Services\Contracts;

use Ulams\CourseVariants\Models\VariantGroup;
use Ulams\CourseVariants\Models\VariantGroupMember;

interface CourseVariantServiceContract
{
    /** The family of a default course, created when it does not exist yet. */
    public function family(int $defaultCourseId): VariantGroup;

    /**
     * Adds (or updates, by course) a variant course to the family of `$defaultCourseId` and hides it from the
     * catalogue. Safe to call again.
     */
    public function addMember(int $defaultCourseId, int $courseId, string $variantKey, string $label, ?string $level, ?string $role, int $position = 0): VariantGroupMember;

    /** Takes a course out of its family and lists it in the catalogue again. */
    public function removeMember(int $courseId): void;

    /** How the default course is named and levelled in the picker (the builder sets it; a hand-made family may leave it). */
    public function setDefaultAudience(int $defaultCourseId, ?string $label, ?string $level, ?string $role): VariantGroup;

    /** Stops offering a variant to new learners; those in it keep it and their progress. */
    public function retire(int $courseId): void;

    /** The family a course belongs to, as the default course or as a member. */
    public function groupForCourse(int $courseId): ?VariantGroup;

    /**
     * @return array{groupId:string,defaultCourseId:int,isDefault:bool,members:array<int,array{courseId:int,variantKey:string,label:string,level:?string,role:?string,position:int}>}|null
     */
    public function describe(int $courseId): ?array;
}
