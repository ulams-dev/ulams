<?php

namespace Ulams\LearnerInsights\Tests\Feature;

use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Ulams\Ai\Models\AiCall;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Models\EntityMapEntry;
use Ulams\CourseBuilder\Models\Session;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;
use Ulams\LearnerInsights\Services\SignalRecorder;
use Ulams\LearnerInsights\Signals\SignalData;
use Ulams\LearnerInsights\Signals\SignalType;
use Ulams\LearnerInsights\Tests\TestCase;
use Ulams\LivingCourse\Database\Seeders\LivingCoursePermissionSeeder;
use Ulams\LivingCourse\Enums\LivingCoursePermissionsEnum;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\LivingCourse\Models\Proposal;
use Ulams\LivingCourse\Models\ProposalItem;
use Ulams\TopicTypeGift\Models\GiftQuestion;
use Ulams\TopicTypeGift\Models\GiftQuiz;

/**
 * "Propose an improvement" from the Insights page (ADR 0181): the estimate and the aggregated evidence first,
 * then a Living Course proposal of kind `insights`. Who may, the minimum group, the daily limit, AI off,
 * elements that were not built with the Course Builder, and what the model is sent.
 */
class ImproveApiTest extends TestCase
{
    private Session $session;

    private Course $course;

    private $author;

    private string $question;

    private string $wrongId;

    private string $rightId;

    private int $questionEntity;

    private int $topicId;

    private int $n = 0;

    /** @var array<int,\Ulams\Courses\Tests\Models\User> */
    private array $learners = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LivingCoursePermissionSeeder::class);
        $this->author = $this->author();
        $this->session = $this->toApplied($this->author);
        $this->course = Course::query()->findOrFail($this->session->course_id);
        $this->course->update(['status' => 'published']);
        $this->course->authors()->syncWithoutDetaching([$this->author->getKey()]);
        $entry = EntityMapEntry::query()->where('session_id', $this->session->id)->where('entity_type', 'gift_question')->orderBy('id')->firstOrFail();
        $this->question = $entry->element_id;
        $this->questionEntity = (int) $entry->entity_id;
        $quiz = GiftQuestion::query()->findOrFail($this->questionEntity)->topic_gift_quiz_id;
        $this->topicId = (int) Topic::query()->where('topicable_type', (new GiftQuiz())->getMorphClass())->where('topicable_id', $quiz)->firstOrFail()->getKey();
        $options = Blueprint::find($this->session->currentVersion->document, $this->question)['node']['options'];
        $this->wrongId = collect($options)->firstWhere('correct', false)['id'];
        $this->rightId = collect($options)->firstWhere('correct', true)['id'];
    }

    private function url(string $element, ?int $course = null): string
    {
        return '/api/admin/learner-insights/courses/' . ($course ?? $this->course->getKey()) . "/elements/{$element}/improve";
    }

    /** Half of `$size` learners pick the same wrong option twice, the rest answer correctly once. */
    private function cohort(int $size = 12): void
    {
        for ($i = 0; $i < $size; $i++) {
            $user = $this->learners[] = $this->student();
            $user->courses()->attach($this->course->getKey());
            $struggler = $i < intdiv($size, 2);
            foreach ($struggler ? [false, false] : [true] as $correct) {
                app(SignalRecorder::class)->record(new SignalData(
                    $user->getKey(), SignalType::QUIZ_ANSWER, 'gift', 'improve-test:' . ++$this->n, now(), $this->course->getKey(), $this->topicId, $this->questionEntity,
                    $correct ? 1.0 : 0.0, ['question_id' => $this->questionEntity, 'correct' => $correct, 'chosen' => [$correct ? $this->rightId : $this->wrongId]],
                ));
            }
        }
    }

    public function testTheEstimateComesFirstWithTheAggregatedEvidenceAndCreatesNothing(): void
    {
        $this->cohort();

        $data = $this->actingAs($this->author, 'api')->postJson($this->url($this->question))->assertOk()->json('data');

        $this->assertSame('estimate', $data['state']);
        $this->assertGreaterThan(0, $data['estimateUsd']);
        $this->assertSame(1, $data['elements']);
        $e = $data['evidence'];
        $this->assertSame(['periodDays', 'learners', 'minGroupSize', 'struggleRate', 'remediationRate', 'recoveryRate', 'helpfulShare', 'topReasons', 'wrongOptions', 'tutorOutOfScope'], array_keys($e));
        $this->assertSame(12, $e['learners']);
        $this->assertSame(0.5, $e['struggleRate']);
        $this->assertSame([['rule' => 'failed_attempts', 'count' => 6]], $e['topReasons']);
        $this->assertSame($this->wrongId, $e['wrongOptions'][0]['optionId']);
        $this->assertSame(0.5, $e['wrongOptions'][0]['share'], '6 of the 12 active learners chose it');
        $this->assertNotSame('', $e['wrongOptions'][0]['label']);
        // aggregates only: not one learner id or name anywhere in the answer
        $this->assertStringNotContainsString('userId', json_encode($data));
        $this->assertSame(0, Proposal::query()->count());
        $this->assertSame(0, AiCall::query()->where('task', 'insights_improve')->count());
    }

    public function testTheInsightsTableMarksTheElementsThatCanBeImproved(): void
    {
        $this->cohort();
        // the same learners open the quiz topic: its element is the quiz itself, which a proposal cannot rewrite
        foreach ($this->learners as $user) {
            app(SignalRecorder::class)->record(new SignalData($user->getKey(), SignalType::TOPIC_OPENED, 'progress', 'improve-quiz:' . ++$this->n, now(), $this->course->getKey(), $this->topicId, null, null, ['revisit' => false]));
        }

        $rows = collect($this->actingAs($this->author, 'api')->getJson("/api/admin/learner-insights/courses/{$this->course->getKey()}/elements")->assertOk()->json('data.elements'))->keyBy('elementKey');

        $this->assertTrue($rows[$this->question]['improvable']);
        $quiz = $rows->first(fn ($r) => $r['elementKey'] !== $this->question && !$r['hidden']);
        $this->assertNotNull($quiz);
        $this->assertFalse($quiz['improvable']);
    }

    public function testWrongOptionsChosenByFewLearnersAreMergedIntoOther(): void
    {
        $this->cohort(12);
        // 3 more learners choose a second wrong option: below the threshold of 5, so they are not named
        $second = collect(Blueprint::find($this->session->currentVersion->document, $this->question)['node']['options'])->where('correct', false)->skip(1)->first();
        if ($second === null) {
            $this->markTestSkipped('the demo question has a single wrong option');
        }
        for ($i = 0; $i < 3; $i++) {
            $user = $this->student();
            $user->courses()->attach($this->course->getKey());
            app(SignalRecorder::class)->record(new SignalData($user->getKey(), SignalType::QUIZ_ANSWER, 'gift', 'improve-few:' . $i, now(), $this->course->getKey(), $this->topicId, $this->questionEntity, 0.0, ['question_id' => $this->questionEntity, 'correct' => false, 'chosen' => [$second['id']]]));
        }

        $options = $this->actingAs($this->author, 'api')->postJson($this->url($this->question))->assertOk()->json('data.evidence.wrongOptions');

        $this->assertSame([$this->wrongId], array_column($options, 'optionId'));
    }

    public function testConfirmingCreatesAndAnalysesAProposalOfKindInsights(): void
    {
        $this->cohort();

        $response = $this->actingAs($this->author, 'api')->postJson($this->url($this->question), ['confirmEstimate' => true])->assertCreated();

        $this->assertSame('started', $response->json('data.state'));
        $summary = $response->json('data.proposal');
        $this->assertSame('insights', $summary['kind']);
        $this->assertNull($summary['fromRevision']);
        $this->assertSame('ready', $summary['status']);
        $this->assertSame(12, $summary['evidence']['learners']);
        $proposal = Proposal::query()->findOrFail($summary['id']);
        $this->assertSame((int) $this->author->getKey(), (int) $proposal->created_by);
        $item = ProposalItem::query()->where('proposal_id', $proposal->id)->sole();
        $this->assertSame($this->question, $item->element_id);
        $this->assertSame('update', $item->kind);
        $this->assertSame('pending', $item->status);
        $this->assertNotEmpty($item->after['citations']);
        $audit = AuditEntry::query()->where('subject_id', $proposal->id)->where('action', 'proposal.created')->sole();
        $this->assertSame(['learners' => 12, 'periodDays' => 30, 'struggleRate' => 0.5, 'remediationRate' => 0.5], $audit->data['evidence'], 'counts only: the six who struggled were offered help');

        // asking again for the same element returns the open proposal instead of a second one
        $again = $this->actingAs($this->author, 'api')->postJson($this->url($this->question), ['confirmEstimate' => true])->assertOk();
        $this->assertSame('kept_existing', $again->json('data.state'));
        $this->assertSame($proposal->id, $again->json('data.proposal.id'));
        $this->actingAs($this->author, 'api')->postJson($this->url($this->question))->assertOk()->assertJsonPath('data.state', 'kept_existing');
        $this->assertSame(1, Proposal::query()->count());
    }

    public function testTheModelRequestCarriesTheEvidenceAndNothingOfAnyLearner(): void
    {
        $this->cohort();
        $learner = $this->learners[0];
        $learner->forceFill(['first_name' => 'Zeldaquill', 'last_name' => 'Featherstone', 'email' => 'zeldaquill.featherstone@learners.example.test'])->save();

        $this->actingAs($this->author, 'api')->postJson($this->url($this->question), ['confirmEstimate' => true])->assertCreated();

        $sent = array_values(array_filter($this->fake()->sent(), fn ($r) => $r->task === 'insights_improve'));
        $this->assertCount(1, $sent);
        $text = \Ulams\LearnerInsights\Tests\Support\ModelRequests::text($sent[0]);
        $this->assertStringContainsString('<learner_evidence aggregated="true">', $text);
        $this->assertStringContainsString('"learners":12', $text);
        // rule names such as failed_attempts are part of the aggregated evidence; everything else of the learner vocabulary is not
        $vocabulary = array_values(array_diff(\Ulams\LearnerInsights\Tests\Support\ModelRequests::learnerVocabulary(), ['failed_attempts']));
        foreach (['Zeldaquill', 'Featherstone', 'learners.example.test', ...$vocabulary] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $text, "the request carries \"$needle\"");
        }
        $this->assertNull(AiCall::query()->where('task', 'insights_improve')->sole()->user_id);
    }

    public function testOnlyTheAuthorOrAReviewerMayPropose(): void
    {
        $this->cohort();
        $learner = $this->learners[0];
        $this->actingAs($learner, 'api')->postJson($this->url($this->question))->assertForbidden();

        // an author of the course who does not own the builder session cannot act on it either
        $coAuthor = $this->tutor();
        $this->course->authors()->attach($coAuthor->getKey());
        $this->actingAs($coAuthor, 'api')->postJson($this->url($this->question))->assertForbidden();

        // an admin may see the insights, and proposes only with living_course_review
        $admin = $this->admin();
        $this->actingAs($admin, 'api')->postJson($this->url($this->question))->assertOk()->assertJsonPath('data.state', 'estimate');
        Role::findByName('admin', 'api')->revokePermissionTo(LivingCoursePermissionsEnum::LIVING_COURSE_REVIEW);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->admin(), 'api')->postJson($this->url($this->question))->assertForbidden();

        // the owner of the session is unaffected
        $this->actingAs($this->author, 'api')->postJson($this->url($this->question))->assertOk();
        $this->assertSame(0, Proposal::query()->count());
    }

    public function testAGroupBelowTheMinimumIsRefusedOnTheServer(): void
    {
        $this->cohort(8);

        $this->actingAs($this->author, 'api')->postJson($this->url($this->question), ['confirmEstimate' => true])->assertStatus(422)->assertJsonPath('code', 'small_group');
        $this->assertSame(0, Proposal::query()->count());
        $this->assertSame(0, AiCall::query()->where('task', 'insights_improve')->count());
    }

    public function testThreeProposalsPerCourseAndDayAreTheLimit(): void
    {
        $this->cohort();
        $this->assertSame(3, config('living_course.cost.insights_proposals_per_session_per_day'), 'the default of the plan');
        config(['living_course.cost.insights_proposals_per_session_per_day' => 1]);
        $lessonElement = EntityMapEntry::query()->where('session_id', $this->session->id)->where('entity_type', 'topic')->orderBy('id')->firstOrFail();
        // learners open the lesson as well, so it has a group of its own
        foreach ($this->learners as $user) {
            app(SignalRecorder::class)->record(new SignalData($user->getKey(), SignalType::TOPIC_OPENED, 'progress', 'improve-open:' . ++$this->n, now(), $this->course->getKey(), (int) $lessonElement->entity_id, null, null, ['revisit' => false]));
        }

        $this->actingAs($this->author, 'api')->postJson($this->url($this->question), ['confirmEstimate' => true])->assertCreated();
        $this->actingAs($this->author, 'api')->postJson($this->url($lessonElement->element_id), ['confirmEstimate' => true])->assertStatus(429)->assertJsonPath('code', 'daily_limit');

        $this->assertSame(1, Proposal::query()->count());
    }

    public function testWithAiDisabledTheEndpointAnswers503(): void
    {
        $this->cohort();
        config(['ai.driver' => 'disabled']);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmClient::class);
        $this->app->forgetInstance(\Ulams\Ai\Contracts\LlmDriver::class);
        $this->app->forgetInstance(\Ulams\LearnerInsights\Http\Controllers\ImproveController::class);
        $this->app->forgetInstance(\Ulams\LivingCourse\Services\InsightsProposalService::class);

        $this->actingAs($this->author, 'api')->postJson($this->url($this->question))->assertStatus(503)->assertJsonPath('code', 'ai_disabled');
    }

    public function testElementsNotBuiltWithTheCourseBuilderAndForeignElementsAre404(): void
    {
        $this->cohort();
        $other = Course::factory()->create(['status' => 'published']);
        $other->authors()->syncWithoutDetaching([$this->author->getKey()]);

        foreach (['topic:12', 'question:7'] as $key) {
            $this->actingAs($this->author, 'api')->postJson($this->url($key))->assertNotFound()->assertJsonPath('code', 'not_builder_element');
        }
        // an element of this course through another course's URL
        $this->actingAs($this->author, 'api')->postJson($this->url($this->question, $other->getKey()))->assertNotFound()->assertJsonPath('code', 'not_builder_element');
        $this->actingAs($this->author, 'api')->postJson($this->url('01arz3ndektsv4rrffq69g5fav'))->assertNotFound();
        // keys outside the allowed shape never reach the controller
        $this->actingAs($this->author, 'api')->postJson($this->url('not-a-key'))->assertNotFound();
        $this->actingAs($this->author, 'api')->postJson('/api/admin/learner-insights/courses/abc/elements/' . $this->question . '/improve')->assertNotFound();
    }

    public function testTheBudgetOfTheCourseBlocksTheAnalysisAndKeepsTheProposal(): void
    {
        $this->cohort();
        config(['living_course.cost.proposal_usd' => 0.000001]);

        $this->actingAs($this->author, 'api')->postJson($this->url($this->question), ['confirmEstimate' => true])->assertStatus(422)->assertJsonPath('code', 'budget_blocked')->assertJsonPath('data.proposal.status', 'budget_blocked');

        $this->assertSame(0, AiCall::query()->where('task', 'insights_improve')->count());
        $this->assertSame(1, Proposal::query()->count());
    }
}
