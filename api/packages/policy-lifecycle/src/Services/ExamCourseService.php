<?php

namespace Ulams\PolicyLifecycle\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Ulams\Compliance\Services\CourseSettings;
use Ulams\CourseBuilder\Apply\BlueprintApplier;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\ReleaseRecorder;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Repositories\Contracts\CourseRepositoryContract;
use Ulams\PolicyLifecycle\Exceptions\PolicyException;
use Ulams\PolicyLifecycle\Models\PolicyVariant;

/**
 * The exam-only course of a department (ADR 0140): the department course's own final test, with every question cited to
 * the policy, as a course of its own, so a minor policy change can be taught as "sit the exam again" while assignment,
 * certificates and reports work unchanged on whole courses. It is derived from the owner-approved department course,
 * not generated: nothing the owner has not already approved is added. It is rebuilt after every approved change, so its
 * questions follow the updated policy, and it stays out of the catalogue.
 */
final class ExamCourseService
{
    public function __construct(
        private readonly BlueprintApplier $applier,
        private readonly VersionService $versions,
        private readonly ReleaseRecorder $releases,
        private readonly CourseRepositoryContract $courses,
        private readonly CourseSettings $settings,
    ) {
    }

    /**
     * Makes the exam course exist and match the department course's current final test.
     *
     * @return int the exam course id
     *
     * @throws PolicyException the department course has no final test to examine
     */
    public function sync(PolicyVariant $variant, Authenticatable $author): int
    {
        $department = Session::query()->find($variant->session_id);
        $applied = $department?->applied_version_id !== null ? Version::query()->find($department->applied_version_id) : null;
        if ($applied === null) {
            throw new PolicyException("{$variant->department_label}: the course is not applied yet, so there is no exam to derive.");
        }
        $document = self::derive($applied->document, $variant->department_label);

        $exam = $variant->exam_session_id !== null ? Session::query()->find($variant->exam_session_id) : null;
        if ($exam === null) {
            $exam = Session::query()->create([
                'author_id' => $author->getAuthIdentifier(),
                'title' => mb_substr($variant->department_label . ' (exam)', 0, 255),
                // pricing: free and no theme, so applying the exam never touches the site or the shop
                'brief' => ['pricing' => ['mode' => 'free']] + array_diff_key((array) $department->brief, array_flip(['theme', 'site', 'pricing', 'audiences'])),
                'status' => Session::APPLIED,
            ]);
            $variant->forceFill(['exam_session_id' => $exam->id])->save();
        }
        $current = $exam->applied_version_id !== null ? Version::query()->find($exam->applied_version_id) : null;
        if ($current !== null && $current->document === $document && $exam->course_id !== null) {
            return (int) $exam->course_id;
        }

        $version = $this->versions->create($exam, $document, $current === null ? 'content' : 'update', 'author', Version::APPROVED, $current, 'Exam of ' . $variant->department_label, null, [], (int) $author->getAuthIdentifier(), $applied->source_revisions);
        $this->versions->setCurrent($exam, $version);
        $courseId = $this->applier->apply($exam, $version, $author, true);
        $this->releases->applied($exam, $version, $courseId, ['status' => Session::APPLIED]);
        $this->expose($courseId, $author);
        $variant->forceFill(['exam_course_id' => $courseId])->save();

        return $courseId;
    }

    /** Published so learners can take it, and `findable` off so it is never listed in the catalogue. */
    private function expose(int $courseId, Authenticatable $author): void
    {
        $previous = Auth::user();
        Auth::setUser($author);
        try {
            $this->courses->update(['status' => CourseStatusEnum::PUBLISHED, 'findable' => false], $courseId);
        } finally {
            $previous !== null ? Auth::setUser($previous) : Auth::forgetUser();
        }
    }

    /**
     * The blueprint of the exam: the department course with every lesson but the first emptied to nothing but its
     * heading, no lesson text and no per-lesson quiz, and the final test kept as it is. A blueprint needs one module with
     * one lesson, so the exam keeps the first lesson as a one-minute heading, with its objectives and citations.
     *
     * @return array<string,mixed>
     *
     * @throws PolicyException
     */
    public static function derive(array $document, string $department): array
    {
        if (!is_array($document['finalTest'] ?? null) || ($document['finalTest']['questions'] ?? []) === []) {
            throw new PolicyException("{$department}: the course has no final test. Regenerate it with a final test; the exam is that test.");
        }
        $first = null;
        foreach (Blueprint::lessons($document) as $item) {
            $first = $item;
            break;
        }
        if ($first === null) {
            throw new PolicyException("{$department}: the course has no lesson.");
        }
        $module = $document['modules'][0];
        $lesson = $first['lesson'];
        $lesson = array_replace($lesson, ['blocks' => [], 'quiz' => null, 'minutes' => 1, 'flags' => [], 'interaction' => null, 'contentType' => 'richtext']);
        unset($lesson['selfChecks'], $lesson['asset'], $lesson['assetRef']);
        $module['lessons'] = [$lesson];
        $module['title'] = 'Exam';
        $module['summary'] = 'Answer the questions of the final test; each cites the policy.';
        unset($module['ai_hash']);

        $course = $document['course'];
        $course['title'] = mb_substr($course['title'] . ' (exam)', 0, 200);

        return array_replace($document, ['course' => $course, 'modules' => [$module], 'pages' => ['landing' => null, 'header' => null]]);
    }
}
