<?php

namespace Ulams\Templates\Tests\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Templates\Facades\Template as FacadesTemplate;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Models\TemplateSection;
use Ulams\Templates\Tests\Mock\TestChannel;
use Ulams\Templates\Tests\Mock\TestEventWithGetters;
use Ulams\Templates\Tests\Mock\TestVariables;
use Ulams\Templates\Tests\TestCase;

/**
 * The template endpoints with the language of a template (M6). Everything is optional, so a client that does
 * not know about languages keeps working.
 */
class TemplatesLocaleApiTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        FacadesTemplate::register(TestEventWithGetters::class, TestChannel::class, TestVariables::class);
    }

    private function body(array $extra = []): array
    {
        return array_merge([
            'name' => 'Friend request',
            'event' => TestEventWithGetters::class,
            'channel' => TestChannel::class,
            'sections' => [
                ['key' => 'title', 'content' => 'Hi'],
                ['key' => 'content', 'content' => '<p>' . TestVariables::VAR_USER_EMAIL . ' ' . TestVariables::VAR_FRIEND_EMAIL . '</p>'],
            ],
        ], $extra);
    }

    private function saved(?string $locale, string $name = 'saved'): Template
    {
        $template = Template::factory()->create(['name' => $name, 'event' => TestEventWithGetters::class, 'channel' => TestChannel::class, 'locale' => $locale]);
        TemplateSection::factory()->create(['template_id' => $template->getKey(), 'key' => 'title', 'content' => 'Hi']);

        return $template;
    }

    public function testATemplateIsCreatedWithoutALanguageWhenNoneIsSent(): void
    {
        $this->authenticateAsAdmin();

        $id = $this->actingAs($this->user, 'api')->postJson('/api/admin/templates', $this->body())
            ->assertStatus(201)->assertJsonPath('data.locale', null)->json('data.id');

        $this->assertNull(Template::find($id)->locale);
    }

    public function testATemplateIsCreatedInALanguageAndTheCodeIsNormalised(): void
    {
        $this->authenticateAsAdmin();

        $id = $this->actingAs($this->user, 'api')->postJson('/api/admin/templates', $this->body(['locale' => ' PL ']))
            ->assertStatus(201)->assertJsonPath('data.locale', 'pl')->json('data.id');

        $this->assertSame('pl', Template::find($id)->locale);
        $this->actingAs($this->user, 'api')->getJson('/api/admin/templates/' . $id)->assertOk()->assertJsonPath('data.locale', 'pl');
    }

    public function testAnEmptyLanguageMeansNoLanguage(): void
    {
        $this->authenticateAsAdmin();

        $id = $this->actingAs($this->user, 'api')->postJson('/api/admin/templates', $this->body(['locale' => '']))
            ->assertStatus(201)->json('data.id');

        $this->assertNull(Template::find($id)->locale);
    }

    #[DataProvider('invalidLocales')]
    public function testALanguageThatIsNotACodeIsRefused(mixed $locale): void
    {
        $this->authenticateAsAdmin();

        $this->actingAs($this->user, 'api')->postJson('/api/admin/templates', $this->body(['locale' => $locale]))
            ->assertStatus(422)->assertJsonValidationErrors('locale');
        $this->assertSame(0, Template::query()->where('name', 'Friend request')->count());
    }

    public static function invalidLocales(): array
    {
        return [['polish'], ['p'], ['pl-PL'], ['9x'], [['pl']]];
    }

    public function testTheLanguageOfATemplateCanBeChangedAndCleared(): void
    {
        $this->authenticateAsAdmin();
        $template = $this->saved(null);

        $this->actingAs($this->user, 'api')->patchJson('/api/admin/templates/' . $template->getKey(), ['locale' => 'zh'])->assertOk();
        $this->assertSame('zh', $template->fresh()->locale);

        $this->actingAs($this->user, 'api')->patchJson('/api/admin/templates/' . $template->getKey(), ['locale' => null])->assertOk();
        $this->assertNull($template->fresh()->locale);

        $this->actingAs($this->user, 'api')->patchJson('/api/admin/templates/' . $template->getKey(), ['locale' => 'english'])->assertStatus(422);
        $this->assertNull($template->fresh()->locale);
    }

    public function testAnUpdateThatDoesNotMentionTheLanguageKeepsIt(): void
    {
        $this->authenticateAsAdmin();
        $template = $this->saved('pl');

        $this->actingAs($this->user, 'api')->patchJson('/api/admin/templates/' . $template->getKey(), ['name' => 'Renamed'])->assertOk();

        $this->assertSame('pl', $template->fresh()->locale);
        $this->assertSame('Renamed', $template->fresh()->name);
    }

    public function testTheListCanBeFilteredByLanguageAndShowsIt(): void
    {
        $this->authenticateAsAdmin();
        $this->saved(null, 'none');
        $pl = $this->saved('pl', 'polish');
        $this->saved('zh', 'chinese');

        $response = $this->actingAs($this->user, 'api')->getJson('/api/admin/templates?event=' . urlencode(TestEventWithGetters::class) . '&locale=pl')->assertOk();
        $response->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pl->getKey())->assertJsonPath('data.0.locale', 'pl');

        $all = $this->actingAs($this->user, 'api')->getJson('/api/admin/templates?event=' . urlencode(TestEventWithGetters::class))->assertOk();
        $this->assertEqualsCanonicalizing([null, 'pl', 'zh'], collect($all->json('data'))->pluck('locale')->all());

        $this->actingAs($this->user, 'api')->getJson('/api/admin/templates?locale=polish')->assertStatus(422);
    }

    public function testAUserWithoutTheTemplatePermissionsCannotUseTheLanguage(): void
    {
        $student = $this->makeStudent();
        $template = $this->saved('pl');

        $this->actingAs($student, 'api')->postJson('/api/admin/templates', $this->body(['locale' => 'pl']))->assertForbidden();
        $this->actingAs($student, 'api')->patchJson('/api/admin/templates/' . $template->getKey(), ['locale' => 'zh'])->assertForbidden();
        $this->actingAs($student, 'api')->getJson('/api/admin/templates?locale=pl')->assertForbidden();
        $this->actingAs($student, 'api')->getJson('/api/admin/templates/' . $template->getKey())->assertForbidden();
        $this->assertSame('pl', $template->fresh()->locale);
    }

    public function testGuestsAreRefused(): void
    {
        $template = $this->saved('pl');

        $this->postJson('/api/admin/templates', $this->body(['locale' => 'pl']))->assertUnauthorized();
        $this->patchJson('/api/admin/templates/' . $template->getKey(), ['locale' => 'zh'])->assertUnauthorized();
        $this->getJson('/api/admin/templates?locale=pl')->assertUnauthorized();
        $this->getJson('/api/admin/templates/' . $template->getKey())->assertUnauthorized();
    }
}
