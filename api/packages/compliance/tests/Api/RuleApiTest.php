<?php

namespace Ulams\Compliance\Tests\Api;

use Illuminate\Support\Facades\Event;
use Ulams\Compliance\Events\PathEnrolled;
use Ulams\Compliance\Models\Assignment;
use Ulams\Compliance\Models\LearningPath;
use Ulams\Compliance\Models\PathEnrollment;
use Ulams\Compliance\Models\Rule;
use Ulams\Compliance\Services\PathService;
use Ulams\Compliance\Services\RuleEngine;
use Ulams\Compliance\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;

class RuleApiTest extends TestCase
{
    use CreatesUsers;

    private LearningPath $path;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([PathEnrolled::class]);
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $this->path = app(PathService::class)->save(['title' => 'Onboarding', 'steps' => [['course_id' => $course->getKey()]]]);
    }

    private function rule(array $conditions, array $extra = []): Rule
    {
        return Rule::query()->create($extra + ['name' => 'r', 'path_id' => $this->path->getKey(), 'conditions' => $conditions, 'due_days' => 14]);
    }

    public function testRulesAreManagedByAdminsAndValidated(): void
    {
        $admin = $this->makeAdmin();
        $body = ['name' => 'Interns', 'path_id' => $this->path->getKey(), 'due_days' => 30, 'conditions' => [['attribute' => 'email', 'operator' => 'ends_with', 'value' => ['@acme.test']]]];

        $this->actingAs($this->makeStudent(), 'api')->postJson('/api/admin/compliance/rules', $body)->assertForbidden();
        $this->actingAs($admin, 'api')->postJson('/api/admin/compliance/rules', ['conditions' => []] + $body)->assertStatus(422);
        $this->actingAs($admin, 'api')->postJson('/api/admin/compliance/rules', ['path_id' => 999] + $body)->assertStatus(422);
        $this->actingAs($admin, 'api')->postJson('/api/admin/compliance/rules', ['conditions' => [['attribute' => 'email', 'operator' => 'sql']]] + $body)->assertStatus(422);

        $id = $this->actingAs($admin, 'api')->postJson('/api/admin/compliance/rules', $body)->assertCreated()->json('data.id');
        $this->actingAs($admin, 'api')->putJson("/api/admin/compliance/rules/{$id}", ['active' => false])->assertOk()->assertJsonPath('data.active', false);
        $this->actingAs($admin, 'api')->getJson('/api/admin/compliance/rules')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin, 'api')->deleteJson("/api/admin/compliance/rules/{$id}")->assertOk();
        $this->actingAs($admin, 'api')->deleteJson("/api/admin/compliance/rules/{$id}")->assertNotFound();
    }

    public function testConditionOperators(): void
    {
        $user = $this->makeStudent(['email' => 'Ada@Acme.test', 'first_name' => 'Ada']);
        $engine = app(RuleEngine::class);
        $matches = fn (array $condition) => $engine->matches($user, $this->rule([$condition]));

        $this->assertTrue($matches(['attribute' => 'first_name', 'operator' => 'equals', 'value' => ['ADA']]));
        $this->assertTrue($matches(['attribute' => 'first_name', 'operator' => 'in', 'value' => ['bob', 'ada']]));
        $this->assertFalse($matches(['attribute' => 'first_name', 'operator' => 'not_equals', 'value' => ['ada']]));
        $this->assertTrue($matches(['attribute' => 'first_name', 'operator' => 'not_equals', 'value' => ['bob']]));
        $this->assertTrue($matches(['attribute' => 'email', 'operator' => 'ends_with', 'value' => ['@acme.test']]));
        $this->assertTrue($matches(['attribute' => 'email', 'operator' => 'starts_with', 'value' => ['ada@']]));
        $this->assertTrue($matches(['attribute' => 'email', 'operator' => 'contains', 'value' => ['acme']]));
        $this->assertTrue($matches(['attribute' => 'email', 'operator' => 'exists']));
        $this->assertFalse($matches(['attribute' => 'nonexistent', 'operator' => 'exists']));
        $this->assertFalse($matches(['attribute' => 'password', 'operator' => 'exists']));
        $this->assertFalse($matches(['attribute' => 'email', 'operator' => 'contains', 'value' => ['']]));
        $this->assertFalse($engine->matches($user, $this->rule([])), 'a rule without conditions matches nobody');
        $this->assertFalse($engine->matches($user, $this->rule([
            ['attribute' => 'email', 'operator' => 'ends_with', 'value' => ['@acme.test']],
            ['attribute' => 'first_name', 'operator' => 'equals', 'value' => ['bob']],
        ])), 'all conditions must hold');
    }

    public function testSavingAUserEnrolsThemWithTheRulesDueDate(): void
    {
        $this->rule([['attribute' => 'email', 'operator' => 'ends_with', 'value' => ['@acme.test']]]);

        $inside = $this->makeStudent(['email' => 'ada@acme.test']);
        $outside = $this->makeStudent(['email' => 'bob@other.test']);

        $enrollment = PathEnrollment::query()->where('user_id', $inside->getKey())->firstOrFail();
        $this->assertSame('rule', $enrollment->source);
        $this->assertEqualsWithDelta(now()->addDays(14)->timestamp, $enrollment->due_at->timestamp, 5);
        $this->assertSame(0, PathEnrollment::query()->where('user_id', $outside->getKey())->count());
        $this->assertSame(1, Assignment::query()->where('user_id', $inside->getKey())->where('source', 'rule')->count());

        // a later save does not enrol twice
        $inside->forceFill(['first_name' => 'Renamed'])->save();
        $this->assertSame(1, PathEnrollment::query()->where('user_id', $inside->getKey())->count());
    }

    public function testAnInactiveRuleDoesNothingAndAChangedAttributeEnrolsLater(): void
    {
        $rule = $this->rule([['attribute' => 'first_name', 'operator' => 'equals', 'value' => ['Safety']]], ['active' => false]);
        $user = $this->makeStudent(['first_name' => 'Safety']);
        $this->assertSame(0, PathEnrollment::query()->count());

        $rule->forceFill(['active' => true])->save();
        $other = $this->makeStudent(['first_name' => 'Other']);
        $this->assertSame(0, PathEnrollment::query()->count());

        $other->forceFill(['first_name' => 'Safety'])->save();
        $this->assertSame(1, PathEnrollment::query()->where('user_id', $other->getKey())->count());
        $this->assertSame(0, PathEnrollment::query()->where('user_id', $user->getKey())->count(), 'a rule is evaluated on change and on schedule, not retroactively on save of others');

        // the scheduled run catches the user who matched before the rule was switched on
        $this->assertSame(1, app(RuleEngine::class)->evaluateAll());
        $this->assertSame(1, PathEnrollment::query()->where('user_id', $user->getKey())->count());
    }

    public function testPreviewAndRunNow(): void
    {
        $admin = $this->makeAdmin();
        $rule = $this->rule([['attribute' => 'first_name', 'operator' => 'equals', 'value' => ['Nightshift']]], ['active' => false]);
        $users = [$this->makeStudent(['first_name' => 'Nightshift']), $this->makeStudent(['first_name' => 'Nightshift']), $this->makeStudent(['first_name' => 'Day'])];

        $this->actingAs($admin, 'api')->postJson("/api/admin/compliance/rules/{$rule->getKey()}/evaluate")
            ->assertOk()->assertJsonPath('data.count', 2)->assertJsonCount(2, 'data.sample');
        $this->assertSame(0, PathEnrollment::query()->count());

        $this->actingAs($admin, 'api')->postJson("/api/admin/compliance/rules/{$rule->getKey()}/evaluate", ['dry_run' => false])
            ->assertOk()->assertJsonPath('data.matched', 2)->assertJsonPath('data.enrolled', 2);
        $this->assertSame(2, PathEnrollment::query()->count());
        $this->actingAs($this->makeStudent(), 'api')->postJson("/api/admin/compliance/rules/{$rule->getKey()}/evaluate")->assertForbidden();
    }
}
