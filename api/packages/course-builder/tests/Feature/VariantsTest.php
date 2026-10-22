<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Release;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Step;
use Ulams\CourseBuilder\Models\Variant;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Pipeline\Llm;
use Ulams\CourseBuilder\Services\ReleaseRecorder;
use Ulams\CourseBuilder\Tests\TestCase;
use Ulams\Courses\Models\Course;
use Ulams\CourseVariants\Services\Contracts\CourseVariantServiceContract;
use Ulams\Courses\Tests\Models\User;

/**
 * Course variants by level and role (ADR 0192, CV-4): the interview asks which audiences to cover, the builder drafts a
 * variant outline per audience from the same sources, the author reviews each as a diff against the base, generation
 * writes only the lessons that changed, every call is logged against its variant, and each approved variant becomes its
 * own LMS course grouped with the base as a family.
 */
class VariantsTest extends TestCase
{
    private const P = '/api/admin/course-builder';

    /** Source → interview with the audiences answered → base outline → lessons → applied base → variant outlines proposed. */
    private function withVariantOutlines(User $author, array $audiences = ['level:beginner', 'level:expert'], string $fixture = 'coffee-brewing.md'): Session
    {
        $session = $this->uploaded($author, $fixture);
        $this->action($author, $session, 'answer', 'interview', ['key' => 'duration', 'value' => ['totalMinutes' => 30, 'lessonMinutes' => 10]])->assertStatus(202);
        $this->action($author, $session, 'answer', 'interview', ['key' => 'assessments', 'value' => ['quiz', 'final']])->assertStatus(202);
        $this->action($author, $session, 'answer', 'interview', ['key' => 'audiences', 'value' => $audiences])->assertStatus(202)->assertJsonPath('data.accepted', true);
        $this->action($author, $session, 'decide_for_me', 'interview')->assertStatus(202);
        $outline = $session->refresh()->current_version_id;
        $this->action($author, $session, 'approve_outline', "outline-{$outline}", ['versionId' => $outline])->assertStatus(202);
        $content = $session->refresh()->current_version_id;
        $this->action($author, $session, 'approve_apply', "apply-{$content}", ['versionId' => $content])->assertStatus(202);

        return $session->refresh();
    }

    private function variant(Session $session, string $key): Variant
    {
        return Variant::query()->where('session_id', $session->id)->where('key', $key)->firstOrFail();
    }

    /** The generate run of a variant (not the base's: runs created in the same second cannot be told apart by time). */
    private function variantRunOf(Session $session, Variant $variant): Run
    {
        return Run::query()->where('session_id', $session->id)->where('kind', 'generate')->get()->first(fn (Run $r) => ($r->input['variantId'] ?? null) === $variant->id) ?? $this->fail('No generate run for the variant.');
    }

    private function approveVariantOutline(User $author, Session $session, Variant $variant): void
    {
        $outline = $variant->refresh()->current_version_id;
        $this->action($author, $session, 'approve_outline', "outline-{$outline}", ['versionId' => $outline])->assertStatus(202)->assertJsonPath('data.accepted', true);
    }

    public function testTheInterviewAsksWhoTheCourseIsForAndStoresTheAudiencesInTheBrief(): void
    {
        $author = $this->author();
        $session = $this->uploaded($author);
        $question = collect($session->stateValue('interview.questions'))->firstWhere('key', 'audiences');
        $this->assertSame('Who is this course for?', $question['label']);
        $this->assertSame(['single'], $question['default']);
        $this->assertStringContainsString('Each extra version costs up to about $', $question['why']);

        $this->action($author, $session, 'answer', 'interview', ['key' => 'audiences', 'value' => ['level:expert', 'developer', 'support']])->assertStatus(202);
        $audiences = $session->refresh()->brief['audiences'];
        $this->assertSame(['Expert, developer', 'Expert, support'], array_column($audiences, 'label'));
        $this->assertSame(['expert', 'expert'], array_column($audiences, 'level'));
        $this->assertSame('author', $session->brief['decidedBy']['audiences']);
        $this->assertSame([], app(\Ulams\CourseBuilder\Blueprint\SchemaRegistry::class)->validate('course-brief/v3', $session->brief));
    }

    public function testOneCourseForEveryoneIsTheDefaultAndCostsNothingExtra(): void
    {
        $author = $this->author();
        $session = $this->uploaded($author);
        $this->action($author, $session, 'decide_for_me', 'interview')->assertStatus(202);
        $this->assertSame([], $session->refresh()->brief['audiences']);
        $this->assertSame(0, Variant::query()->count());
    }

    public function testMoreAudiencesThanTheCapAreRefusedWithAnExplanation(): void
    {
        config(['course_builder.variants.max' => 2]);
        $author = $this->author();
        $session = $this->uploaded($author);
        $this->action($author, $session, 'answer', 'interview', ['key' => 'audiences', 'value' => ['level:beginner', 'level:intermediate', 'level:expert']])
            ->assertStatus(202)->assertJsonPath('data.accepted', false);
        $this->assertArrayNotHasKey('audiences', (array) $session->refresh()->brief);
        $this->assertStringContainsString('the limit is 2', \Ulams\CourseBuilder\Models\Event::query()->where('session_id', $session->id)->where('type', 'TEXT_MESSAGE_CONTENT')->latest('id')->first()->payload['delta']);
    }

    public function testVariantsAreOffWhenTheOperatorSetsTheCapToZero(): void
    {
        config(['course_builder.variants.max' => 0]);
        $session = $this->uploaded($this->author());
        $this->assertNull(collect($session->stateValue('interview.questions'))->firstWhere('key', 'audiences'));
    }

    public function testApplyingTheBaseDraftsAnOutlinePerAudienceAsAnOutlineDiffAgainstTheBase(): void
    {
        $author = $this->author();
        $session = $this->withVariantOutlines($author);

        $variants = Variant::query()->where('session_id', $session->id)->orderBy('position')->get();
        $this->assertSame(['beginner', 'expert'], $variants->pluck('key')->all());
        $base = Version::query()->find($session->applied_version_id);
        foreach ($variants as $variant) {
            $outline = Version::query()->findOrFail($variant->current_version_id);
            $this->assertSame('outline', $outline->kind);
            $this->assertSame(Version::PROPOSED, $outline->status);
            $this->assertSame($variant->id, $outline->variant_id);
            $this->assertSame($base->id, $outline->parent_id);
            $this->assertSame($base->id, $variant->based_on_version_id);
            $this->assertStringEndsWith("({$variant->label})", $outline->document['course']['title']);
            $this->assertSame($base->document['course']['id'], $outline->document['course']['id'], 'a variant keeps the base element ids');
        }
        // the proposal is reviewed with the builder's OutlineDiff, against the base
        $this->assertTrue((bool) $session->refresh()->stateValue('surfaces.outline-' . $variants[1]->current_version_id . '.open'));
        // the base branch is untouched: still one current version, the applied one
        $this->assertSame($base->id, $session->applied_version_id);
        $this->assertSame(Session::APPLIED, $session->status);

        // the plan: an expert skips the first lesson and condenses the second; everything else is kept
        $expert = $this->variant($session, 'expert');
        $actions = array_values(array_column($expert->plan['lessons'], 'action'));
        $this->assertSame(['skip', 'condense'], array_slice($actions, 0, 2));
        $this->assertSame(array_fill(0, count($actions) - 2, 'keep'), array_slice($actions, 2));
        $this->assertSame(count($actions) - 1, Blueprint::stats($expert->currentVersion->document)['lessons']);
        $this->assertNull($expert->currentVersion->document['finalTest'], 'the final test is written again when lessons changed');
    }

    public function testTheVariantPlanSeesTheFragmentIndexAndNotTheFullSourceAndTreatsTheBriefAsData(): void
    {
        $author = $this->author();
        $this->withVariantOutlines($author, ['level:expert']);
        $call = collect($this->fake()->sent())->last(fn ($r) => $r->task === 'variant_outline');
        $text = implode("\n", array_map(fn ($b) => $b->text, $call->blocks));
        $this->assertStringContainsString('<fragment_index untrusted="true">', $text);
        $this->assertStringNotContainsString('<source_document', $text);
        $this->assertStringContainsString('<task_input>', $text);
    }

    public function testApprovingAVariantOutlineWritesOnlyTheLessonsThatChangedAndLogsEveryCallAgainstTheVariant(): void
    {
        $author = $this->author();
        $session = $this->withVariantOutlines($author);
        $expert = $this->variant($session, 'expert');
        $basePlanCalls = AiCall::query()->forSubject(Variant::SUBJECT_TYPE, $expert->id)->where('task', 'variant_outline')->count();
        $this->assertSame(1, $basePlanCalls);

        $this->approveVariantOutline($author, $session, $expert);

        $run = $this->variantRunOf($session, $expert);
        $this->assertSame($expert->id, $run->input['variantId']);
        $this->assertSame('finished', $run->status);
        // one rewritten lesson (condense): one lesson step, plus a quiz for it, plus the final test; no metadata step
        $keys = Step::query()->where('run_id', $run->id)->pluck('key')->all();
        $this->assertCount(1, array_filter($keys, fn ($k) => str_starts_with($k, 'lesson:')));
        $this->assertContains('final_test', $keys);
        $this->assertNotContains('metadata', $keys);

        $calls = AiCall::query()->forSubject(Variant::SUBJECT_TYPE, $expert->id)->pluck('task')->all();
        $this->assertSame(1, count(array_keys($calls, 'variant_lesson', true)));
        $this->assertNotContains('lesson', $calls, 'a variant uses its own task');
        $this->assertGreaterThan(0, AiCall::query()->forSubject(Variant::SUBJECT_TYPE, $expert->id)->sum('input_tokens'));
        // the session's running cost includes its variants
        $this->assertGreaterThanOrEqual(AiCall::query()->forSubject(Variant::SUBJECT_TYPE, $expert->id)->sum('cost_micro_usd'), Llm::cost($session)['usedMicroUsd']);

        $version = $expert->refresh()->currentVersion;
        $this->assertSame('content', $version->kind);
        $this->assertSame(Version::PROPOSED, $version->status);
        $this->assertSame($expert->id, $version->variant_id);
        // every generated block cites fragments of the session's sources; kept lessons were copied, not regenerated
        $known = array_fill_keys($session->fragmentIds(), true);
        $this->assertSame([], array_values(array_filter(Checks::blueprint($version->document, $known), fn ($e) => !str_starts_with($e, 'warning: '))));
        $base = Version::query()->find($session->applied_version_id)->document;
        $baseLessons = collect(iterator_to_array(Blueprint::lessons($base), false))->keyBy(fn ($i) => $i['lesson']['id']);
        foreach (Blueprint::lessons($version->document) as $item) {
            $lesson = $item['lesson'];
            $this->assertNotEmpty($lesson['blocks']);
            if (($expert->plan['lessons'][$lesson['id']]['action'] ?? null) === 'keep') {
                $this->assertSame($baseLessons[$lesson['id']]['lesson']['blocks'], $lesson['blocks']);
            }
        }
        // the session itself did not move
        $this->assertSame(Session::APPLIED, $session->refresh()->status);
        $this->assertTrue((bool) $session->stateValue('surfaces.variant-' . $version->id . '.open'));
    }

    public function testAVariantBlockThatCitesNothingRealFailsItsStepAndCanBeRetried(): void
    {
        $author = $this->author();
        $session = $this->withVariantOutlines($author, ['level:expert']);
        $expert = $this->variant($session, 'expert');
        $bad = ['blocks' => [['kind' => 'paragraph', 'markdown' => 'Invented claim.', 'citations' => ['frg_aaaaaaaaaaaa'], 'objectiveIds' => ['x']]]];
        $this->fake()->queueJson('variant_lesson', $bad)->queueJson('variant_lesson', $bad)->queueJson('variant_lesson', $bad);

        $this->approveVariantOutline($author, $session, $expert);

        $run = $this->variantRunOf($session, $expert);
        $this->assertSame('needs_attention', $run->status);
        $failed = Step::query()->where('run_id', $run->id)->where('status', 'failed')->firstOrFail();
        $this->assertStringStartsWith('lesson:', $failed->key);
        $this->assertNotSame(Version::PROPOSED, $expert->refresh()->currentVersion?->status, 'nothing is proposed for review while a lesson failed');

        $this->action($author, $session, 'retry_step', "progress-{$run->id}", ['stepId' => $failed->id])->assertStatus(202);
        $this->assertSame('finished', $run->refresh()->status);
        $this->assertSame(Version::PROPOSED, $expert->refresh()->currentVersion->status);
    }

    public function testApprovingAVariantAppliesItToItsOwnCourseGroupedWithTheBase(): void
    {
        $author = $this->author();
        $session = $this->withVariantOutlines($author);
        $expert = $this->variant($session, 'expert');
        $this->approveVariantOutline($author, $session, $expert);
        $version = $expert->refresh()->current_version_id;
        $baseEntries = EntityMapEntry::query()->where('session_id', $session->id)->count();
        $this->assertNull($expert->course_id);
        $this->assertSame(0, EntityMapEntry::branch($session->id, $expert->id)->count(), 'nothing is written to the LMS before the author approves');

        $this->action($author, $session, 'approve_variant', "variant-{$version}", ['versionId' => $version])->assertStatus(202);

        $expert->refresh();
        $this->assertSame(Variant::APPROVED, $expert->status);
        $this->assertSame($version, $expert->applied_version_id);
        $this->assertNotNull($expert->course_id);
        $this->assertNotSame($session->course_id, $expert->course_id);
        $course = Course::query()->findOrFail($expert->course_id);
        $this->assertStringEndsWith('(Expert)', $course->title);
        $this->assertFalse((bool) $course->findable, 'a variant is hidden from the catalogue; the family shows once');
        $this->assertTrue((bool) Course::query()->findOrFail($session->course_id)->findable);
        $this->assertSame('advanced', $course->level);
        $this->assertSame(Blueprint::stats($expert->currentVersion->document)['modules'] + 1, $course->lessons()->count(), 'one LMS lesson per module and one for the final test');

        // the base branch keeps its own map; the variant has its own, with the same element ids
        $this->assertSame($baseEntries, EntityMapEntry::query()->where('session_id', $session->id)->count());
        $this->assertGreaterThan(0, EntityMapEntry::branch($session->id, $expert->id)->count());
        $element = $session->currentVersion->document['course']['id'];
        $this->assertNotSame(
            EntityMapEntry::branch($session->id)->where('element_id', $element)->where('entity_type', 'course')->value('entity_id'),
            EntityMapEntry::branch($session->id, $expert->id)->where('element_id', $element)->where('entity_type', 'course')->value('entity_id'),
        );

        // the family
        $family = app(CourseVariantServiceContract::class)->describe((int) $session->course_id);
        $this->assertTrue($family['isDefault']);
        $this->assertSame((int) $session->course_id, $family['defaultCourseId']);
        $this->assertSame([['courseId' => (int) $expert->course_id, 'variantKey' => 'expert', 'label' => 'Expert', 'level' => 'expert', 'role' => null, 'position' => 2]], $family['members']);
        $this->assertSame($family['groupId'], app(CourseVariantServiceContract::class)->describe((int) $expert->course_id)['groupId']);

        // the API describes it
        $row = collect($this->actingAs($author, 'api')->getJson(self::P . "/sessions/{$session->id}/variants")->assertOk()->json('data.variants'))->firstWhere('key', 'expert');
        $this->assertSame($expert->course_id, $row['courseId']);
        $this->assertSame($family['groupId'], $row['family']['groupId']);
    }

    /** The gap CV-4 left: a variant's apply set `applied_version_id` directly and wrote no release (ADR 0192, CV-2). */
    public function testApprovingAVariantWritesItsOwnReleaseAndLeavesTheBaseReleasesAlone(): void
    {
        $author = $this->author();
        $session = $this->withVariantOutlines($author);
        $expert = $this->variant($session, 'expert');
        $this->approveVariantOutline($author, $session, $expert);
        $version = $expert->refresh()->current_version_id;
        $baseReleases = Release::query()->where('session_id', $session->id)->whereNull('variant_id')->count();
        $this->assertSame(1, $baseReleases);
        $this->assertSame(0, Release::query()->where('variant_id', $expert->id)->count(), 'nothing is released before the author approves');

        $this->action($author, $session, 'approve_variant', "variant-{$version}", ['versionId' => $version])->assertStatus(202);

        $expert->refresh();
        $releases = Release::query()->where('variant_id', $expert->id)->get();
        $this->assertCount(1, $releases);
        $release = $releases[0];
        $this->assertSame([1, 'generate', $version, (int) $expert->course_id, null], [$release->number, $release->trigger, $release->version_id, $release->course_id, $release->previous_release_id]);
        $this->assertSame($session->id, $release->session_id);
        $this->assertSame($author->getKey(), $release->approved_by);
        $this->assertTrue($release->summary['first']);
        $this->assertSame($version, $expert->applied_version_id);
        $this->assertSame(Variant::APPROVED, $expert->status);
        $this->assertSame($baseReleases, Release::query()->where('session_id', $session->id)->whereNull('variant_id')->count(), 'the base keeps its own numbering');

        // the course-versions API lists the variant course's releases, not the base's
        $list = $this->actingAs($author, 'api')->getJson("/api/admin/course-versions/courses/{$expert->course_id}/releases")->assertOk();
        $this->assertSame([$release->id], array_column($list->json('data.releases'), 'id'));
        $list->assertJsonPath('data.variantId', $expert->id)->assertJsonPath('data.releases.0.variantId', $expert->id);
        $base = $this->actingAs($author, 'api')->getJson("/api/admin/course-versions/courses/{$session->course_id}/releases")->assertOk();
        $this->assertNotContains($release->id, array_column($base->json('data.releases'), 'id'));

        // applying the version that is already live writes nothing; a later version is the next number of this variant only
        $recorder = app(ReleaseRecorder::class);
        $this->assertNull($recorder->appliedVariant($expert, Version::query()->findOrFail($version), (int) $expert->course_id));
        $second = Version::query()->findOrFail($session->applied_version_id);
        $next = $recorder->appliedVariant($expert, $second, (int) $expert->course_id, [], 'author');
        $this->assertSame([2, $release->id, 'author'], [$next->number, $next->previous_release_id, $next->trigger]);
        $this->assertSame($baseReleases, Release::query()->where('session_id', $session->id)->whereNull('variant_id')->count());
    }

    public function testBackfillGivesAnAlreadyAppliedVariantItsFirstRelease(): void
    {
        $author = $this->author();
        $session = $this->withVariantOutlines($author, ['level:expert']);
        $expert = $this->variant($session, 'expert');
        $this->approveVariantOutline($author, $session, $expert);
        $version = $expert->refresh()->current_version_id;
        $this->action($author, $session, 'approve_variant', "variant-{$version}", ['versionId' => $version])->assertStatus(202);
        Release::query()->where('variant_id', $expert->id)->delete();

        $this->artisan('course-builder:backfill-releases', ['--dry-run' => true])->expectsOutput('Would record 1 release(s).')->assertSuccessful();
        $this->artisan('course-builder:backfill-releases')->expectsOutput('Recorded 1 release(s).')->assertSuccessful();
        $this->artisan('course-builder:backfill-releases')->expectsOutput('Recorded 0 release(s).')->assertSuccessful();

        $release = Release::query()->where('variant_id', $expert->id)->sole();
        $this->assertSame([1, 'generate', $version], [$release->number, $release->trigger, $release->version_id]);
        $this->assertSame(1, Release::query()->where('session_id', $session->id)->whereNull('variant_id')->count());
    }

    public function testPublishingTheBasePublishesTheFamilyButNotAnUnapprovedVariant(): void
    {
        $author = $this->author();
        $session = $this->withVariantOutlines($author);
        $expert = $this->variant($session, 'expert');
        $this->approveVariantOutline($author, $session, $expert);
        $version = $expert->refresh()->current_version_id;
        $this->action($author, $session, 'approve_variant', "variant-{$version}", ['versionId' => $version])->assertStatus(202);
        $beginner = $this->variant($session, 'beginner');

        $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$session->id}/publish", ['acknowledgedWarnings' => true])->assertOk();

        $this->assertTrue(Course::query()->findOrFail($session->course_id)->is_published);
        $this->assertTrue(Course::query()->findOrFail($expert->refresh()->course_id)->is_published);
        $this->assertNull($beginner->refresh()->course_id, 'an unapproved variant is never applied');
    }

    public function testThePublishCheckCoversEveryApprovedVariant(): void
    {
        $author = $this->author();
        $session = $this->withVariantOutlines($author, ['level:expert']);
        $expert = $this->variant($session, 'expert');
        $this->approveVariantOutline($author, $session, $expert);
        $version = $expert->refresh()->current_version_id;
        $this->action($author, $session, 'approve_variant', "variant-{$version}", ['versionId' => $version])->assertStatus(202);

        $clean = $this->actingAs($author, 'api')->getJson(self::P . "/sessions/{$session->id}/publish-check")->assertOk();
        $this->assertNotContains('variant_not_applied', array_column($clean->json('data.blocking'), 'code'));

        // a newer version of the variant that is not applied yet blocks publishing the family
        $expert->forceFill(['current_version_id' => $session->applied_version_id])->save();
        $blocked = $this->actingAs($author, 'api')->getJson(self::P . "/sessions/{$session->id}/publish-check")->assertOk();
        $this->assertContains('variant_not_applied', array_column($blocked->json('data.blocking'), 'code'));
        $this->assertStringContainsString('Expert', collect($blocked->json('data.blocking'))->firstWhere('code', 'variant_not_applied')['message']);
    }

    public function testRejectingAVariantCreatesNothing(): void
    {
        $author = $this->author();
        $session = $this->withVariantOutlines($author, ['level:expert']);
        $expert = $this->variant($session, 'expert');
        $this->approveVariantOutline($author, $session, $expert);
        $version = $expert->refresh()->current_version_id;
        $courses = Course::query()->count();

        $this->actingAs($author, 'api')->postJson(self::P . "/versions/{$version}/reject")->assertOk();

        $this->assertSame(Version::REJECTED, Version::query()->find($version)->status);
        $this->assertSame($courses, Course::query()->count());
        $this->assertNull($expert->refresh()->course_id);
        $this->assertSame(0, EntityMapEntry::branch($session->id, $expert->id)->count());
    }

    public function testRejectingAVariantOutlineRegeneratesThePlanWithTheComment(): void
    {
        $author = $this->author();
        $session = $this->withVariantOutlines($author, ['level:expert']);
        $expert = $this->variant($session, 'expert');
        $first = $expert->current_version_id;

        $this->action($author, $session, 'reject_outline', "outline-{$first}", ['versionId' => $first, 'comment' => 'Keep the grinding lesson'])->assertStatus(202);

        $this->assertSame(Version::REJECTED, Version::query()->find($first)->status);
        $second = $expert->refresh()->current_version_id;
        $this->assertNotSame($first, $second);
        $this->assertSame('Keep the grinding lesson', Version::query()->find($second)->reason);
        $call = collect($this->fake()->sent())->last(fn ($r) => $r->task === 'variant_outline');
        $this->assertStringContainsString('<author_feedback>Keep the grinding lesson</author_feedback>', $call->blocks[count($call->blocks) - 1]->text);
        $this->assertSame(Version::PROPOSED, Version::query()->find($second)->status);
    }

    public function testApprovingABaseVersionDoesNotSupersedeAVariantProposal(): void
    {
        $author = $this->author();
        $session = $this->withVariantOutlines($author, ['level:expert']);
        $expert = $this->variant($session, 'expert');
        $proposal = Version::query()->find($expert->current_version_id);
        $this->assertSame(Version::PROPOSED, $proposal->status);

        $versions = app(\Ulams\CourseBuilder\Services\VersionService::class);
        $other = $versions->create($session, $session->currentVersion->document, 'outline', 'ai', Version::PROPOSED, $session->currentVersion);
        $versions->approve($other, $author->getKey());

        $this->assertSame(Version::PROPOSED, $proposal->refresh()->status);
        $this->assertNotContains($proposal->id, array_column($this->actingAs($author, 'api')->getJson(self::P . "/sessions/{$session->id}/versions")->json('data.versions'), 'id'), 'the base history lists only base versions');
    }

    public function testTheVariantRunStageReturnsAnErrorUntilTheBaseIsApplied(): void
    {
        $author = $this->author();
        $session = $this->toApplyReview($author);
        $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$session->id}/runs", ['messages' => [], 'forwardedProps' => ['stage' => 'variants', 'variants' => [['level' => 'expert']]]])
            ->assertStatus(409);
        $this->assertSame(0, Variant::query()->count());
    }

    public function testVariantsCanBeAddedLaterThroughTheRunStageAndTheApi(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->assertSame(0, Variant::query()->count());

        $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$session->id}/runs", ['messages' => [], 'forwardedProps' => ['stage' => 'variants', 'variants' => [['level' => 'expert', 'role' => 'developer']]]])
            ->assertStatus(202);

        $variant = $this->variant($session, 'expert-developer');
        $this->assertSame('Expert, developer', $variant->label);
        $this->assertSame(Version::PROPOSED, $variant->currentVersion->status);

        $response = $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$session->id}/variants", ['audiences' => [['level' => 'beginner']], 'start' => true])->assertCreated();
        $this->assertNotNull($response->json('data.runId'));
        $this->assertSame(2, Variant::query()->where('session_id', $session->id)->count());
    }

    public function testTheCapAppliesToEveryWayOfAddingVariants(): void
    {
        config(['course_builder.variants.max' => 2]);
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$session->id}/variants", ['audiences' => [['level' => 'beginner'], ['level' => 'expert'], ['role' => 'support']]])
            ->assertStatus(422);
        $this->assertSame(0, Variant::query()->count());
        $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$session->id}/variants", ['audiences' => [['level' => 'beginner'], ['level' => 'expert']]])->assertCreated();
        $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$session->id}/variants", ['audiences' => [['role' => 'support']]])->assertStatus(422);

        // a retired variant frees a place
        $beginner = $this->variant($session, 'beginner');
        $this->actingAs($author, 'api')->deleteJson(self::P . "/variants/{$beginner->id}")->assertOk()->assertJsonPath('data.status', 'retired');
        $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$session->id}/variants", ['audiences' => [['role' => 'support']]])->assertCreated();
    }

    public function testTheListShowsTheEstimateTheCapAndRenamesAndReorders(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$session->id}/variants", ['audiences' => [['level' => 'expert'], ['role' => 'support']]])->assertCreated();

        $list = $this->actingAs($author, 'api')->getJson(self::P . "/sessions/{$session->id}/variants")->assertOk();
        $this->assertSame(6, $list->json('data.cap'));
        $this->assertSame(2, $list->json('data.active'));
        $this->assertTrue($list->json('data.base.ready'));
        $this->assertSame(2, $list->json('data.estimate.variants'));
        $this->assertGreaterThan(0, $list->json('data.estimate.estimateMicroUsd'));

        $support = $this->variant($session, 'support');
        $this->actingAs($author, 'api')->patchJson(self::P . "/variants/{$support->id}", ['label' => 'Support team', 'position' => 0])->assertOk()->assertJsonPath('data.label', 'Support team');
        $this->assertSame('support', collect($this->actingAs($author, 'api')->getJson(self::P . "/sessions/{$session->id}/variants")->json('data.variants'))->first()['key']);
    }

    public function testTheSessionBudgetStopsTheVariantRunAcrossAllVariants(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $session->forceFill(['budget_micro_usd' => 1])->save();

        $this->actingAs($author, 'api')->postJson(self::P . "/sessions/{$session->id}/variants", ['audiences' => [['level' => 'expert']], 'start' => true])->assertCreated();

        $run = Run::query()->where('session_id', $session->id)->where('kind', 'variant_plan')->firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('Budget reached', (string) $run->error);
        $this->assertTrue($session->refresh()->stateValue('budgetReached'));
        $this->assertNull($this->variant($session, 'expert')->current_version_id);
    }

    public function testExistingSessionsAndBriefsKeepWorkingAsOneCourse(): void
    {
        $author = $this->author();
        $session = $this->toApplied($author);
        $this->assertSame(0, Variant::query()->count());
        $brief = (array) $session->brief;
        unset($brief['audiences']);
        $this->assertSame([], app(\Ulams\CourseBuilder\Blueprint\SchemaRegistry::class)->validate('course-brief/v3', $brief));
        $this->assertSame([], app(\Ulams\CourseBuilder\Blueprint\SchemaRegistry::class)->validate('course-brief/v2', $brief));
        $this->assertSame(0, EntityMapEntry::withoutGlobalScope('base')->where('session_id', $session->id)->where('variant_id', '!=', '')->count());
    }
}
