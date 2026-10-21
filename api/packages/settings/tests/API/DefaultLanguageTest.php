<?php

namespace Ulams\Settings\Tests\API;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Settings\Database\Seeders\DatabaseSeeder;
use Ulams\Settings\Database\Seeders\PermissionTableSeeder;
use Ulams\Settings\Models\Setting;
use Ulams\Settings\Tests\TestCase;

/**
 * The tenant default language is the public setting `global.language`: en, pl or zh. The learner web app
 * falls back to English for any other code without saying so, so the settings API refuses it. The setting
 * lives in the tenant's own database and is written through the existing admin endpoints (admin role only).
 */
class DefaultLanguageTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionTableSeeder::class);
        $this->seed(DatabaseSeeder::class);
        Setting::query()->where(['group' => 'global', 'key' => 'language'])->delete();

        $this->admin = config('auth.providers.users.model')::factory()->create();
        $this->admin->guard_name = 'api';
        $this->admin->assignRole('admin');
    }

    private function payload(string $value): array
    {
        return ['group' => 'global', 'key' => 'language', 'type' => 'text', 'value' => $value, 'public' => true, 'enumerable' => true];
    }

    #[Test]
    public function testCreatesTheDefaultLanguageAndPublishesIt(): void
    {
        foreach (['en', 'pl', 'zh'] as $code) {
            Setting::query()->where(['group' => 'global', 'key' => 'language'])->delete();
            $this->actingAs($this->admin, 'api')->postJson('/api/admin/settings', $this->payload($code))->assertOk();
            $this->getJson('/api/settings')->assertOk()->assertJsonPath('data.global.language', $code);
        }
    }

    #[Test]
    public function testRefusesALanguageTheLearnerAppDoesNotKnow(): void
    {
        foreach (['de', 'PL', 'pl-PL', 'Polish', ''] as $code) {
            $this->actingAs($this->admin, 'api')->postJson('/api/admin/settings', $this->payload($code))->assertUnprocessable();
        }
        $this->assertSame(0, Setting::query()->where(['group' => 'global', 'key' => 'language'])->count());
    }

    #[Test]
    public function testUpdatingTheStoredSettingIsCheckedToo(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/settings', $this->payload('pl'))->assertOk();
        $id = Setting::query()->where(['group' => 'global', 'key' => 'language'])->value('id');

        // the body names neither group nor key: the stored row decides which rule applies
        $this->actingAs($this->admin, 'api')->putJson("/api/admin/settings/{$id}", ['value' => 'de'])->assertUnprocessable();
        $this->actingAs($this->admin, 'api')->putJson("/api/admin/settings/{$id}", ['value' => 'zh'])->assertOk();
        $this->assertSame('zh', Setting::query()->find($id)->value);
    }

    #[Test]
    public function testOtherSettingsAreNotAffected(): void
    {
        $body = ['group' => 'global', 'key' => 'companyName', 'type' => 'text', 'value' => 'Not a language code', 'public' => true, 'enumerable' => true];
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/settings', $body)->assertOk();
    }

    #[Test]
    public function testNobodyWithoutAnAdminRoleCanChangeIt(): void
    {
        $this->postJson('/api/admin/settings', $this->payload('pl'))->assertUnauthorized();

        $student = $this->makeStudent();
        $this->actingAs($student, 'api')->postJson('/api/admin/settings', $this->payload('pl'))->assertForbidden();
        $this->assertSame(0, Setting::query()->where(['group' => 'global', 'key' => 'language'])->count());
    }
}
