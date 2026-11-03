<?php

namespace Ulams\Compliance\Services;

use Illuminate\Support\Facades\DB;
use Ulams\Compliance\Models\Competency;
use Ulams\Compliance\Models\CourseCompetency;
use Ulams\Core\Models\User;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\TemplatesPdf\Models\FabricPDF;

/**
 * What a learner can do: the confirmed competencies of the courses they have done. A course counts with a valid
 * certificate, or, when it issues none, once it is finished. The profile feeds the `competency` attribute of the
 * enrolment rules (for example "enrol everyone who lacks Fire safety") and is the data Learner Insights reads.
 */
class SkillProfileService
{
    public function __construct(private StepProgress $progress)
    {
    }

    /**
     * @return list<array{competency_id: int, name: string, code: string|null, area: string|null, courses: list<array{course_id: int, title: string|null}>, earned_at: string|null, expires_at: string|null}>
     */
    public function forUser(User $user): array
    {
        $courseIds = $this->doneCourseIds($user);
        if ($courseIds === []) {
            return [];
        }

        $rows = CourseCompetency::query()
            ->with('competency.parent')
            ->where('status', CourseCompetency::CONFIRMED)
            ->whereIn('course_id', $courseIds)
            ->get();
        $titles = Course::query()->whereIn('id', $courseIds)->pluck('title', 'id');
        $certificates = FabricPDF::query()
            ->where('user_id', $user->getKey())
            ->where('assignable_type', Course::class)
            ->whereIn('assignable_id', $courseIds)
            ->whereNull('revoked_at')
            ->get()
            ->keyBy('assignable_id');

        return $rows->groupBy('competency_id')->map(function ($group) use ($titles, $certificates) {
            /** @var Competency $competency */
            $competency = $group->first()->competency;
            $dates = $group->map(fn (CourseCompetency $r) => $certificates[$r->course_id] ?? null)->filter();

            return [
                'competency_id' => $competency->getKey(),
                'name' => $competency->name,
                'code' => $competency->code,
                'area' => $competency->parent?->name,
                'courses' => $group->map(fn (CourseCompetency $r) => ['course_id' => $r->course_id, 'title' => $titles[$r->course_id] ?? null])->values()->all(),
                'earned_at' => $dates->min('created_at')?->toIso8601String(),
                // the competency lapses with the last certificate that proves it; none of them expires = no expiry
                'expires_at' => $dates->isNotEmpty() && $dates->every(fn (FabricPDF $c) => $c->expires_at) ? $dates->max('expires_at')->toIso8601String() : null,
            ];
        })->sortBy('name')->values()->all();
    }

    /** @return list<string> lower-cased names, for rules */
    public function names(User $user): array
    {
        return array_map(fn (array $skill) => mb_strtolower($skill['name']), $this->forUser($user));
    }

    /** @return list<int> */
    private function doneCourseIds(User $user): array
    {
        $candidates = CourseUserPivot::query()->where('user_id', $user->getKey())->pluck('course_id')
            ->merge(FabricPDF::query()->where('user_id', $user->getKey())->where('assignable_type', Course::class)->pluck('assignable_id'))
            ->unique();
        $tagged = DB::table('compliance_course_competencies')->where('status', CourseCompetency::CONFIRMED)->whereIn('course_id', $candidates)->pluck('course_id')->unique();

        return $tagged->filter(fn ($courseId) => $this->progress->isSatisfied($user, (int) $courseId))->map(fn ($id) => (int) $id)->values()->all();
    }
}
