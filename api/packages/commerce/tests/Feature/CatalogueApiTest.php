<?php

namespace Ulams\Commerce\Tests\Feature;

use Ulams\Commerce\Database\Seeders\CommercePermissionSeeder;
use Ulams\Commerce\Models\AccessPass;
use Ulams\Commerce\Models\Bundle;
use Ulams\Commerce\Models\SellablePrice;
use Ulams\Commerce\Tests\Support\OrderFlowTestCase;
use Ulams\Core\Enums\UserRole;
use Ulams\Courses\Models\User;
use Ulams\Core\Tests\Concerns\ForeignTenantToken;
use Ulams\CourseAccess\Models\Course;

/** Prices, bundles and access passes through the admin API: who may, what is validated, and tenant isolation. */
class CatalogueApiTest extends OrderFlowTestCase
{
    use ForeignTenantToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CommercePermissionSeeder::class);
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('auth.guards.api', ['driver' => 'passport', 'provider' => 'users']);
    }

    private function admin(): User
    {
        return tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::ADMIN));
    }

    private function makeCourse(string $status = 'published'): Course
    {
        return Course::factory()->create(['title' => 'Brewing basics', 'status' => $status]);
    }

    public function testAnAdminSetsAPriceAndTheProductIsPushedToTheProvider(): void
    {
        $course = $this->makeCourse();

        $this->actingAs($this->admin(), 'api')->putJson("/api/admin/commerce/products/course/{$course->getKey()}", ['price_minor' => 4900, 'currency' => 'eur'])
            ->assertOk()->assertJsonPath('data.price_minor', 4900)->assertJsonPath('data.currency', 'EUR')->assertJsonPath('data.for_sale', true);

        $this->assertSame(4900, $this->sylius->products["variant-course-{$course->getKey()}"]->priceMinor);
        $this->assertTrue($this->sylius->products["variant-course-{$course->getKey()}"]->enabled);
    }

    public function testTheListShowsPriceStateAndTheProductAtTheProvider(): void
    {
        $course = $this->makeCourse();
        $draft = $this->makeCourse('draft');
        $admin = $this->admin();
        $this->actingAs($admin, 'api')->putJson("/api/admin/commerce/products/course/{$course->getKey()}", ['price_minor' => 4900, 'currency' => 'EUR'])->assertOk();
        $this->actingAs($admin, 'api')->putJson("/api/admin/commerce/products/course/{$draft->getKey()}", ['price_minor' => 100, 'currency' => 'EUR', 'for_sale' => false])->assertOk();

        $rows = collect($this->actingAs($admin, 'api')->getJson('/api/admin/commerce/products')->assertOk()->json('data'));

        $first = $rows->firstWhere('id', $course->getKey());
        $this->assertSame(['course', 'Brewing basics', 4900, true, true], [$first['type'], $first['name'], $first['price_minor'], $first['for_sale'], $first['published']]);
        $this->assertSame("variant-course-{$course->getKey()}", $first['product']['external_id']);
        $this->assertNotNull($first['product']['synced_at']);
        $this->assertNull($first['product']['sync_error']);
        $second = $rows->firstWhere('id', $draft->getKey());
        $this->assertFalse($second['for_sale']);
        $this->assertFalse($second['product']['enabled']);
    }

    public function testASellableThatDoesNotExistIsNotFoundAndBadPricesAreRefused(): void
    {
        $admin = $this->admin();
        $course = $this->makeCourse();

        $this->actingAs($admin, 'api')->putJson('/api/admin/commerce/products/course/999999', ['price_minor' => 100, 'currency' => 'EUR'])->assertNotFound();
        $this->actingAs($admin, 'api')->putJson('/api/admin/commerce/products/cow/1', ['price_minor' => 100, 'currency' => 'EUR'])->assertNotFound();
        foreach ([['price_minor' => 0, 'currency' => 'EUR'], ['price_minor' => 100, 'currency' => 'euro'], ['currency' => 'EUR'], ['price_minor' => 'x', 'currency' => 'EUR']] as $body) {
            $this->actingAs($admin, 'api')->putJson("/api/admin/commerce/products/course/{$course->getKey()}", $body)->assertStatus(422);
        }
        $this->assertSame(0, SellablePrice::query()->count());
    }

    public function testOnlyThePermissionHolderMayUseThem(): void
    {
        $course = $this->makeCourse();
        $student = User::factory()->create();

        foreach ([['GET', '/api/admin/commerce/products', []], ['PUT', "/api/admin/commerce/products/course/{$course->getKey()}", ['price_minor' => 1, 'currency' => 'EUR']],
            ['POST', '/api/admin/commerce/bundles', ['name' => 'x', 'course_ids' => [$course->getKey()]]], ['POST', '/api/admin/commerce/access-passes', ['name' => 'x', 'subject_type' => 'course', 'subject_id' => $course->getKey(), 'days' => 3]]] as [$method, $uri, $body]) {
            $this->sendAs(null, $method, $uri, $body)->assertUnauthorized();
            $this->actingAs($student, 'api')->json($method, $uri, $body)->assertForbidden();
            \Illuminate\Support\Facades\Auth::forgetGuards();
        }
        $this->assertSame([], $this->sylius->syncCalls);
    }

    public function testABundleIsCreatedWithItsCoursesAndSoldOnceItHasAPrice(): void
    {
        [$a, $b] = [$this->makeCourse(), $this->makeCourse()];
        $admin = $this->admin();

        $id = $this->actingAs($admin, 'api')->postJson('/api/admin/commerce/bundles', ['name' => 'Coffee set', 'course_ids' => [$b->getKey(), $a->getKey()]])
            ->assertCreated()->assertJsonPath('data.course_ids', [$a->getKey(), $b->getKey()])->json('data.id');
        $this->actingAs($admin, 'api')->putJson("/api/admin/commerce/products/bundle/{$id}", ['price_minor' => 9900, 'currency' => 'EUR'])->assertOk();

        $this->assertSame('Coffee set', $this->sylius->products["variant-bundle-{$id}"]->name);
        $this->actingAs($admin, 'api')->postJson('/api/admin/commerce/bundles', ['name' => 'Bad', 'course_ids' => [999999]])->assertStatus(422);
        $this->actingAs($admin, 'api')->postJson('/api/admin/commerce/bundles', ['name' => 'Empty', 'course_ids' => []])->assertStatus(422);
        $this->assertSame(1, Bundle::query()->count());
    }

    public function testAnAccessPassNeedsAnExistingSubjectAndPositiveDays(): void
    {
        $course = $this->makeCourse();
        $admin = $this->admin();

        $id = $this->actingAs($admin, 'api')->postJson('/api/admin/commerce/access-passes', ['name' => '30 days', 'subject_type' => 'course', 'subject_id' => $course->getKey(), 'days' => 30])
            ->assertCreated()->json('data.id');
        $this->actingAs($admin, 'api')->putJson("/api/admin/commerce/products/access_pass/{$id}", ['price_minor' => 1900, 'currency' => 'EUR'])->assertOk();

        $this->assertSame('30 days', $this->sylius->products["variant-access_pass-{$id}"]->name);
        foreach ([['subject_id' => 999999], ['days' => 0], ['days' => 99999], ['subject_type' => 'order']] as $override) {
            $this->actingAs($admin, 'api')->postJson('/api/admin/commerce/access-passes', $override + ['name' => 'x', 'subject_type' => 'course', 'subject_id' => $course->getKey(), 'days' => 5])->assertStatus(422);
        }
        $this->assertSame(1, AccessPass::query()->count());
    }

    public function testATokenIssuedByAnotherTenantIsRefused(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('t')->accessToken;
        $this->sendAs($token, 'GET', '/api/admin/commerce/products')->assertOk();

        $this->switchToTenantWithOwnKeys();

        $this->sendAs($token, 'GET', '/api/admin/commerce/products')->assertUnauthorized();
        $this->sendAs($token, 'PUT', '/api/admin/commerce/products/course/1', ['price_minor' => 1, 'currency' => 'EUR'])->assertUnauthorized();
        $this->sendAs($token, 'POST', '/api/admin/commerce/bundles', ['name' => 'x', 'course_ids' => [1]])->assertUnauthorized();
        $this->sendAs($token, 'POST', '/api/admin/commerce/access-passes', ['name' => 'x', 'subject_type' => 'course', 'subject_id' => 1, 'days' => 1])->assertUnauthorized();
    }

    public function testTheScopeMapPutsTheRoutesInTheCommerceArea(): void
    {
        foreach (['GET api/admin/commerce/products' => false, 'PUT api/admin/commerce/products/course/1' => true, 'POST api/admin/commerce/bundles' => true, 'POST api/admin/commerce/access-passes' => true] as $route => $write) {
            [$method, $path] = explode(' ', $route);
            $this->assertSame(['area' => 'commerce', 'write' => $write], \Ulams\Auth\Support\TokenScopes::requirement($path, $method), $route);
        }
    }
}
