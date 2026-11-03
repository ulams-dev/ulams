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
 * `theme.theme` is a preset of the shared list and `theme.accent` a hex colour: the learner site would otherwise
 * fall back to a default without saying so. `theme.logo` is only written by the logo endpoint.
 */
class ThemeSettingsTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionTableSeeder::class);
        $this->seed(DatabaseSeeder::class);
        Setting::query()->where('group', 'theme')->delete();
        $this->admin = $this->makeAdmin();
    }

    private function body(string $key, string $value): array
    {
        return ['group' => 'theme', 'key' => $key, 'type' => 'text', 'value' => $value, 'public' => true, 'enumerable' => true];
    }

    #[Test]
    public function testEveryPresetOfTheListIsAccepted(): void
    {
        foreach (['coffee', 'oncall', 'nightsky', 'gravity', 'poland', 'ulam'] as $preset) {
            Setting::query()->where(['group' => 'theme', 'key' => 'theme'])->delete();
            $this->actingAs($this->admin, 'api')->postJson('/api/admin/settings', $this->body('theme', $preset))->assertOk();
            $this->getJson('/api/settings')->assertJsonPath('data.theme.theme', $preset);
        }
    }

    #[Test]
    public function testAPresetOutsideTheListIsRefused(): void
    {
        foreach (['platform', 'Poland', 'dark', ''] as $value) {
            $this->actingAs($this->admin, 'api')->postJson('/api/admin/settings', $this->body('theme', $value))->assertUnprocessable();
        }
        $this->assertSame(0, Setting::query()->where(['group' => 'theme', 'key' => 'theme'])->count());
    }

    #[Test]
    public function testTheAccentIsASixDigitHexColour(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/settings', $this->body('accent', '#C8102e'))->assertOk();
        foreach (['c8102e', '#fff', 'red', 'rgb(1,2,3)', '#c8102e; background:red', '#c8102eaa', ''] as $value) {
            $this->actingAs($this->admin, 'api')->postJson('/api/admin/settings', $this->body('accent', $value))->assertUnprocessable();
        }
        $this->assertSame('#C8102e', Setting::query()->where(['group' => 'theme', 'key' => 'accent'])->value('value'));
    }

    #[Test]
    public function testUpdatingTheStoredSettingIsCheckedToo(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/settings', $this->body('theme', 'coffee'))->assertOk();
        $id = Setting::query()->where(['group' => 'theme', 'key' => 'theme'])->value('id');

        $this->actingAs($this->admin, 'api')->putJson("/api/admin/settings/{$id}", ['value' => 'neon'])->assertUnprocessable();
        $this->actingAs($this->admin, 'api')->putJson("/api/admin/settings/{$id}", ['value' => 'poland'])->assertOk();
        $this->assertSame('poland', Setting::query()->find($id)->value);
    }

    #[Test]
    public function testTheLogoCannotBeSetThroughTheGenericEndpoints(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/settings', $this->body('logo', 'https://evil.example/x.png'))->assertUnprocessable();
        $this->assertSame(0, Setting::query()->where(['group' => 'theme', 'key' => 'logo'])->count());
    }

    #[Test]
    public function testTheAltTextHasALimit(): void
    {
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/settings', $this->body('logoAlt', 'Acme Coffee'))->assertOk();
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/settings', $this->body('logoAlt', str_repeat('a', 121)))->assertUnprocessable();
    }

    #[Test]
    public function testOtherGroupsAreNotAffected(): void
    {
        $body = ['group' => 'other', 'key' => 'theme', 'type' => 'text', 'value' => 'whatever', 'public' => true, 'enumerable' => true];
        $this->actingAs($this->admin, 'api')->postJson('/api/admin/settings', $body)->assertOk();
    }

    #[Test]
    public function testStudentsAndAnonymousCannotWrite(): void
    {
        $this->postJson('/api/admin/settings', $this->body('theme', 'poland'))->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')->postJson('/api/admin/settings', $this->body('theme', 'poland'))->assertForbidden();
        $this->assertSame(0, Setting::query()->where(['group' => 'theme', 'key' => 'theme'])->count());
    }
}
