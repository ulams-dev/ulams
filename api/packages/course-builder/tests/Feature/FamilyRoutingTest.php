<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Variant;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VariantService;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Courses\Models\Course;
use Ulams\CourseVariants\Contracts\PlacementSource;
use Ulams\CourseVariants\Contracts\TopicCorrespondence;
use Ulams\CourseVariants\Models\VariantGroup;
use Ulams\CourseVariants\Models\VariantGroupMember;
use Ulams\CourseVariants\Services\Contracts\CourseVariantServiceContract;
use Ulams\Courses\Tests\Models\User;

/**
 * What the builder gives learner routing (ADR 0192, CV-5): the topics two variant courses share, the placement questions,
 * the base course's name in the picker, and retiring a variant.
 */
class FamilyRoutingTest extends TestCase
{
    private function appliedFamily(User $author): Session
    {
        $session = $this->uploaded($author);
        $this->action($author, $session, 'answer', 'interview', ['key' => 'duration', 'value' => ['totalMinutes' => 30, 'lessonMinutes' => 10]])->assertStatus(202);
        $this->action($author, $session, 'answer', 'interview', ['key' => 'assessments', 'value' => ['quiz', 'final']])->assertStatus(202);
        $this->action($author, $session, 'answer', 'interview', ['key' => 'audiences', 'value' => ['level:expert']])->assertStatus(202)->assertJsonPath('data.accepted', true);
        $this->action($author, $session, 'decide_for_me', 'interview')->assertStatus(202);
        $outline = $session->refresh()->current_version_id;
        $this->action($author, $session, 'approve_outline', "outline-{$outline}", ['versionId' => $outline])->assertStatus(202);
        $content = $session->refresh()->current_version_id;
        $this->action($author, $session, 'approve_apply', "apply-{$content}", ['versionId' => $content])->assertStatus(202);
        $expert = Variant::query()->where('session_id', $session->id)->where('key', 'expert')->firstOrFail();
        $variantOutline = $expert->refresh()->current_version_id;
        $this->action($author, $session, 'approve_outline', "outline-{$variantOutline}", ['versionId' => $variantOutline])->assertStatus(202);
        $version = $expert->refresh()->current_version_id;
        $this->action($author, $session, 'approve_variant', "variant-{$version}", ['versionId' => $version])->assertStatus(202);

        return $session->refresh();
    }

    public function testTheBaseCourseIsNamedInThePickerByTheBriefLevel(): void
    {
        $session = $this->appliedFamily($this->author());

        $group = VariantGroup::query()->where('default_course_id', $session->course_id)->firstOrFail();

        $level = \Ulams\CourseBuilder\Pipeline\BriefService::LEVEL_OF_BRIEF[$session->brief['level']];
        $this->assertSame($level, $group->default_level);
        $this->assertSame(ucfirst($level), $group->default_label);
    }

    public function testTopicsThatAreTheSameContentInBothCoursesArePairedAndTheRestAreNot(): void
    {
        $session = $this->appliedFamily($this->author());
        $expert = Variant::query()->where('session_id', $session->id)->where('key', 'expert')->firstOrFail();
        $pairs = $this->app->make(TopicCorrespondence::class)->pairs((int) $session->course_id, (int) $expert->course_id);

        $this->assertNotEmpty($pairs, 'a variant inherits lessons from the base');
        $baseTopics = EntityMapEntry::branch($session->id)->whereIn('entity_type', ['topic', 'interaction_topic'])->pluck('element_id', 'entity_id')->all();
        $expertTopics = EntityMapEntry::branch($session->id, $expert->id)->whereIn('entity_type', ['topic', 'interaction_topic'])->pluck('element_id', 'entity_id')->all();
        foreach ($pairs as $from => $to) {
            $this->assertSame($baseTopics[$from], $expertTopics[$to], 'a pair is the same element in both branches');
        }
        $this->assertLessThanOrEqual(count($baseTopics), count($pairs));
        $quizTopics = EntityMapEntry::branch($session->id)->where('entity_type', 'quiz_topic')->pluck('entity_id')->all();
        $this->assertSame([], array_values(array_intersect(array_keys($pairs), $quizTopics)), 'quizzes are retaken, never paired');

        // the pairing is symmetric and refuses courses of different sessions or hand-made courses
        $back = $this->app->make(TopicCorrespondence::class)->pairs((int) $expert->course_id, (int) $session->course_id);
        $this->assertSame(array_flip($pairs), $back);
        $this->assertSame([], $this->app->make(TopicCorrespondence::class)->pairs((int) $session->course_id, Course::factory()->create()->getKey()));
    }

    public function testPlacementQuestionsComeFromTheCitedQuizQuestionsOfTheFamily(): void
    {
        $session = $this->appliedFamily($this->author());
        $expert = Variant::query()->where('session_id', $session->id)->where('key', 'expert')->firstOrFail();
        $group = VariantGroup::query()->with('members')->where('default_course_id', $session->course_id)->firstOrFail();
        $courses = [['courseId' => (int) $session->course_id, 'level' => $group->default_level ?? 'beginner'], ['courseId' => (int) $expert->course_id, 'level' => 'expert']];

        $questions = $this->app->make(PlacementSource::class)->questions($courses);

        $this->assertNotEmpty($questions);
        $baseDoc = Version::query()->findOrFail($session->applied_version_id)->document;
        $baseIds = array_column($baseDoc['finalTest']['questions'] ?? [], 'id');
        foreach (Blueprint::lessons($baseDoc) as $item) {
            foreach ($item['lesson']['quiz']['questions'] ?? [] as $q) {
                $baseIds[] = $q['id'];
            }
        }
        foreach ($questions as $q) {
            $this->assertNotEmpty($q['question']['citations'], 'every placement question is cited');
            $this->assertContains($q['level'], [$group->default_level ?? 'beginner', 'expert']);
            $this->assertArrayHasKey('correct', $q['question']['options'][0]);
            if ($q['level'] === ($group->default_level ?? 'beginner') && $q['courseId'] === (int) $session->course_id) {
                $this->assertContains($q['elementId'], $baseIds);
            }
        }
    }

    public function testRetiringAVariantTakesItOutOfThePicker(): void
    {
        $author = $this->author();
        $session = $this->appliedFamily($author);
        $expert = Variant::query()->where('session_id', $session->id)->where('key', 'expert')->firstOrFail();
        $this->assertNull(VariantGroupMember::query()->where('course_id', $expert->course_id)->value('retired_at'));

        $this->app->make(VariantService::class)->retire($expert);

        $this->assertNotNull(VariantGroupMember::query()->where('course_id', $expert->course_id)->value('retired_at'));
        $ids = collect($this->getJson("/api/courses/{$session->course_id}/variants")->assertOk()->json('data.variants'))->pluck('courseId')->all();
        $this->assertNotContains((int) $expert->course_id, $ids);
        $this->assertSame(Variant::RETIRED, $expert->refresh()->status);
        $this->assertNotNull($this->app->make(CourseVariantServiceContract::class)->groupForCourse((int) $expert->course_id));
    }
}
