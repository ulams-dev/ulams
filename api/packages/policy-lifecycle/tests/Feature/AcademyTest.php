<?php

namespace Ulams\PolicyLifecycle\Tests\Feature;

use RuntimeException;
use Spatie\Permission\Models\Permission;
use Ulams\Auth\Enums\AuthPermissionsEnum;
use Ulams\LivingCourse\Models\AuditEntry;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyVariant;
use Ulams\PolicyLifecycle\Tests\TestCase;
use Ulams\Tenancy\Services\Contracts\TenantCommandRunnerContract;

/** An academy of its own for a department (ADR 0141, amendment): a tenant created from a template, the course moved there. */
class AcademyTest extends TestCase
{
    /** @var array<int,array{0:string,1:array<int,string>}> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        // the tenancy defaults (host patterns); the package is not booted in these tests, and this process is the tenant "acme"
        config(['ulams_tenancy' => require __DIR__ . '/../../../tenancy/src/config.php']);
        config(['ulams_tenancy.tenant_slug' => 'acme', 'ulams_tenancy.new_sites' => true]);
    }

    private function operator()
    {
        $user = $this->admin();
        Permission::findOrCreate(AuthPermissionsEnum::PLATFORM_ADMIN, 'api');
        $user->givePermissionTo(AuthPermissionsEnum::PLATFORM_ADMIN);

        return $user;
    }

    /** Owns policies and builds courses, but is not a platform administrator. */
    private function plainOwner()
    {
        $user = $this->author();
        $user->givePermissionTo(Permission::findOrCreate('policy_owner', 'api'));

        return $user;
    }

    private function runner(?string $fail = null, int $failTimes = 1): void
    {
        $this->calls = [];
        $this->app->instance(TenantCommandRunnerContract::class, new class ($this->calls, $fail, $failTimes) implements TenantCommandRunnerContract {
            public function __construct(private array &$calls, private ?string $fail, private int $failTimes)
            {
            }

            public function run(string $host, array $arguments): string
            {
                $this->calls[] = [$host, $arguments];
                if ($this->fail !== null && $arguments[0] === $this->fail && $this->failTimes-- > 0) {
                    throw new RuntimeException("`{$arguments[0]}` failed: the limit is 5");
                }

                return $arguments[0] === 'course-builder:session:import' ? "noise\n" . json_encode(['sessionId' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'invited' => true]) : 'ok';
            }
        });
    }

    /** A policy whose Engineering department has its course generated up to the outline. */
    private function generated($owner): array
    {
        $id = $this->createPolicy($owner, ['departments' => [['label' => 'Engineering', 'seniority' => 'senior', 'priorExperience' => 'expert']]]);
        $this->generateAll($owner, $id);

        return [Policy::query()->findOrFail($id), Policy::query()->findOrFail($id)->variants->sole()];
    }

    private function url(Policy $policy, PolicyVariant $variant): string
    {
        return "/api/admin/policies/{$policy->id}/variants/{$variant->id}/academy";
    }

    public function testItNeedsAPlatformAdministratorAndNewSitesAndNeverCallsThePlatformOtherwise(): void
    {
        $this->runner();
        $owner = $this->plainOwner();
        [$policy, $variant] = $this->generated($owner);

        $this->actingAs($owner, 'api')->postJson($this->url($policy, $variant), ['theme' => 'coffee'])->assertForbidden();
        config(['ulams_tenancy.new_sites' => false]);
        $this->actingAs($this->operator(), 'api')->postJson($this->url($policy, $variant), ['theme' => 'coffee'])->assertForbidden();
        config(['ulams_tenancy.new_sites' => true, 'ulams_tenancy.tenant_slug' => null]);
        $this->actingAs($this->operator(), 'api')->postJson($this->url($policy, $variant), ['theme' => 'coffee'])->assertForbidden();
        $this->actingAs($owner, 'api')->deleteJson($this->url($policy, $variant))->assertForbidden();

        $this->assertSame([], $this->calls);
        $this->assertNull($variant->refresh()->academy);
    }

    public function testTheStatusTellsWhetherTheCallerMayAskAndWhichThemesExist(): void
    {
        $owner = $this->plainOwner();
        [$policy, $variant] = $this->generated($owner);

        $plain = $this->actingAs($owner, 'api')->getJson($this->url($policy, $variant))->assertOk();
        $plain->assertJsonPath('data.available', false)->assertJsonPath('data.academy', null);
        $this->assertContains('coffee', $plain->json('data.themes'));
        $this->actingAs($this->operator(), 'api')->getJson($this->url($policy, $variant))->assertOk()->assertJsonPath('data.available', true);
        $this->actingAs($owner, 'api')->getJson("/api/admin/policies/{$policy->id}")->assertOk()->assertJsonPath('data.academyAvailable', false);
    }

    public function testTheAcademyIsCreatedFromATemplateWithTheDepartmentsThemeAndTheCourseIsMovedThere(): void
    {
        $this->runner();
        $operator = $this->operator();
        [$policy, $variant] = $this->generated($operator);

        $this->actingAs($operator, 'api')->postJson($this->url($policy, $variant), ['theme' => 'nightsky', 'accent' => '#c2552d', 'template' => 'policy-academy'])->assertStatus(202);

        $academy = $variant->refresh()->academy;
        $this->assertSame('done', $academy['status']);
        $this->assertMatchesRegularExpression('/^acme[a-z0-9]{12}$/', $academy['slug']);
        $this->assertStringStartsWith('acmeengineer', $academy['slug']);
        $this->assertSame('Remote work and data handling / Engineering', $academy['name']);
        $this->assertSame(['nightsky', '#C2552D', 'policy-academy'], [$academy['theme'], $academy['accent'], $academy['template']]);
        $this->assertSame("http://{$academy['slug']}.app.localhost/studio/s/01ARZ3NDEKTSV4RRFFQ69G5FAV", $academy['studioUrl']);
        $this->assertSame("http://{$academy['slug']}.admin.localhost", $academy['adminUrl']);
        $this->assertTrue($academy['invited']);

        [$create, $import] = $this->calls;
        $this->assertSame('api.localhost', $create[0], 'the tenant is created under the platform host');
        $this->assertSame(['ulams:tenant:create', $academy['slug'], '--users=0', '--name=Remote work and data handling / Engineering', '--theme=nightsky', '--accent=#C2552D', '--template=policy-academy', '--derived-from=acme', '--purpose=policy_department', '--reference=' . $variant->id, '--requested-by=' . $operator->email], $create[1]);
        $this->assertSame("{$academy['slug']}.localhost", $import[0], 'the session is imported in the new tenant');
        $this->assertSame('course-builder:session:import', $import[1][0]);
        $this->assertContains('--author-email=' . $operator->email, $import[1]);
        // the audit chain records the request and the result
        $actions = AuditEntry::query()->where('subject_type', 'policy')->pluck('action')->all();
        $this->assertContains('policy.academy.requested', $actions);
        $this->assertContains('policy.academy.provisioned', $actions);
        $this->assertSame([], glob(sys_get_temp_dir() . '/policy-academy-' . $variant->id . '-*.tar'), 'the archive is removed');
    }

    public function testTheDefaultTemplateComesFromTheConfiguration(): void
    {
        $this->runner();
        config(['ulams_policy_lifecycle.academy_template' => 'default-academy']);
        $operator = $this->operator();
        [$policy, $variant] = $this->generated($operator);

        $this->actingAs($operator, 'api')->postJson($this->url($policy, $variant), [])->assertStatus(202);

        $this->assertContains('--template=default-academy', $this->calls[0][1]);
        $this->assertNotContains('--theme=nightsky', $this->calls[0][1]);
    }

    public function testInvalidChoicesAreRefusedBeforeAnythingIsCreated(): void
    {
        $this->runner();
        $operator = $this->operator();
        [$policy, $variant] = $this->generated($operator);

        foreach ([['theme' => 'neon'], ['accent' => 'red'], ['template' => 'Bad Template']] as $body) {
            $this->actingAs($operator, 'api')->postJson($this->url($policy, $variant), $body)->assertStatus(422);
        }
        $this->assertSame([], $this->calls);
        $this->assertNull($variant->refresh()->academy);
    }

    public function testThereIsNothingToMoveBeforeTheCourseIsGenerated(): void
    {
        $this->runner();
        $operator = $this->operator();
        $id = $this->createPolicy($operator, ['departments' => [['label' => 'Engineering']]]);
        $policy = Policy::query()->findOrFail($id);

        $this->actingAs($operator, 'api')->postJson($this->url($policy, $policy->variants->sole()), ['theme' => 'coffee'])->assertStatus(409);
        $this->assertSame([], $this->calls);
    }

    public function testAnAcademyThatExistsIsNotCreatedAgainAndAnotherOwnerCannotTouchIt(): void
    {
        $this->runner();
        $operator = $this->operator();
        [$policy, $variant] = $this->generated($operator);
        $this->actingAs($operator, 'api')->postJson($this->url($policy, $variant), ['theme' => 'coffee'])->assertStatus(202);
        $calls = count($this->calls);

        $this->actingAs($operator, 'api')->postJson($this->url($policy, $variant), ['theme' => 'coffee'])->assertStatus(409);
        $this->assertCount($calls, $this->calls);

        $stranger = $this->author();
        Permission::findOrCreate(AuthPermissionsEnum::PLATFORM_ADMIN, 'api');
        $stranger->givePermissionTo(AuthPermissionsEnum::PLATFORM_ADMIN);
        $stranger->givePermissionTo(Permission::findOrCreate('policy_owner', 'api'));
        $this->actingAs($stranger, 'api')->getJson($this->url($policy, $variant))->assertForbidden();
        $this->actingAs($stranger, 'api')->deleteJson($this->url($policy, $variant))->assertForbidden();
        $other = $this->createPolicy($operator, ['title' => 'Other', 'departments' => [['label' => 'Sales']]]);
        $this->actingAs($operator, 'api')->getJson("/api/admin/policies/{$other}/variants/{$variant->id}/academy")->assertNotFound();
    }

    public function testAFailureIsExplainedAndAskingAgainResumesWithoutMovingTheCourseTwice(): void
    {
        $this->runner('course-builder:session:import');
        $operator = $this->operator();
        [$policy, $variant] = $this->generated($operator);

        $this->actingAs($operator, 'api')->postJson($this->url($policy, $variant), ['theme' => 'coffee'])->assertStatus(202);

        $academy = $variant->refresh()->academy;
        $this->assertSame('failed', $academy['status']);
        $this->assertStringContainsString('course-builder:session:import', $academy['error']);
        $slug = $academy['slug'];
        $this->assertContains('policy.academy.failed', AuditEntry::query()->pluck('action')->all());

        $this->actingAs($operator, 'api')->postJson($this->url($policy, $variant), ['theme' => 'coffee'])->assertStatus(202);

        $again = $variant->refresh()->academy;
        $this->assertSame(['done', $slug], [$again['status'], $again['slug']], 'the same tenant is resumed, not another one');
        $creates = array_filter($this->calls, fn ($call) => $call[1][0] === 'ulams:tenant:create');
        $this->assertCount(2, $creates);
        foreach ($creates as $call) {
            $this->assertContains('--reference=' . $variant->id, $call[1]);
        }
    }

    public function testAnImportThatSucceededIsNotRepeatedWhenAnEarlierRunStoppedAfterIt(): void
    {
        $this->runner();
        $operator = $this->operator();
        [$policy, $variant] = $this->generated($operator);
        $variant->forceFill(['academy' => ['status' => 'failed', 'slug' => 'acmeengineerabcd', 'name' => 'X', 'theme' => null, 'accent' => null, 'template' => null, 'requestedBy' => 'o@example.test', 'sessionId' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'error' => 'timeout']])->save();

        $this->actingAs($operator, 'api')->postJson($this->url($policy, $variant), [])->assertStatus(202);

        $this->assertSame(['ulams:tenant:create'], array_map(fn ($call) => $call[1][0], $this->calls), 'only the tenant steps run again');
        $this->assertSame('done', $variant->refresh()->academy['status']);
    }

    public function testThePlatformsRefusalIsShownToTheOwner(): void
    {
        $this->runner('ulams:tenant:create');
        $operator = $this->operator();
        [$policy, $variant] = $this->generated($operator);

        $this->actingAs($operator, 'api')->postJson($this->url($policy, $variant), ['theme' => 'coffee'])->assertStatus(202);

        $academy = $variant->refresh()->academy;
        $this->assertSame('failed', $academy['status']);
        $this->assertStringContainsString('the limit is 5', $academy['error']);
        $this->assertSame(1, count($this->calls), 'nothing was imported');
    }

    public function testRemovingTheAcademyDeletesItsTenantAndClearsTheRequest(): void
    {
        $this->runner();
        $operator = $this->operator();
        [$policy, $variant] = $this->generated($operator);
        $this->actingAs($operator, 'api')->postJson($this->url($policy, $variant), ['theme' => 'coffee'])->assertStatus(202);
        $slug = $variant->refresh()->academy['slug'];

        $this->actingAs($operator, 'api')->deleteJson($this->url($policy, $variant))->assertOk()->assertJsonPath('data.academy', null);

        $last = end($this->calls);
        $this->assertSame(['api.localhost', ['ulams:tenant:delete', $slug, '--force']], $last);
        $this->assertNull($variant->refresh()->academy);
        $this->assertContains('policy.academy.removed', AuditEntry::query()->pluck('action')->all());
        $this->actingAs($operator, 'api')->deleteJson($this->url($policy, $variant))->assertNotFound();
        // and a new one can be asked for
        $this->actingAs($operator, 'api')->postJson($this->url($policy, $variant), ['theme' => 'coffee'])->assertStatus(202);
        $this->assertSame('done', $variant->refresh()->academy['status'], (string) ($variant->academy['error'] ?? ''));
    }

    public function testAFailedRemovalKeepsTheRequestSoNothingIsLost(): void
    {
        $this->runner();
        $operator = $this->operator();
        [$policy, $variant] = $this->generated($operator);
        $this->actingAs($operator, 'api')->postJson($this->url($policy, $variant), ['theme' => 'coffee'])->assertStatus(202);
        $this->runner('ulams:tenant:delete');

        $this->actingAs($operator, 'api')->deleteJson($this->url($policy, $variant))->assertStatus(502);

        $this->assertSame('done', $variant->refresh()->academy['status']);
    }
}
