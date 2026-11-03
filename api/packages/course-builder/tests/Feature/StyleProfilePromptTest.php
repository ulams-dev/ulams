<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Blueprint\SchemaRegistry;
use Ulams\CourseBuilder\Models\StyleProfile;
use Ulams\CourseBuilder\Pipeline\BriefService;
use Ulams\CourseBuilder\Tests\TestCase;

/** ADR 0196, SG-3: the approved profile is typed data in the cached brief block; the style guide is never in a prompt. */
class StyleProfilePromptTest extends TestCase
{
    private const P = '/api/admin/course-builder/style-profiles';

    /** A guide uploaded and approved, so its text exists in the database and could leak if anything were careless. */
    private function approvedFromGuide(string $fixture = 'style-guide.md', array $extra = []): string
    {
        $id = $this->actingAs($this->author(), 'api')->post(self::P, ['file' => $this->fixture($fixture)] + $extra, ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->actingAs($this->admin(), 'api')->postJson(self::P . "/{$id}/approve")->assertOk();

        return $id;
    }

    private function manual(string $name, array $profile, array $extra = []): string
    {
        $id = $this->actingAs($this->author(), 'api')->postJson(self::P, ['name' => $name, 'profile' => $profile] + $extra)->assertCreated()->json('data.id');
        $this->actingAs($this->admin(), 'api')->postJson(self::P . "/{$id}/approve")->assertOk();

        return $id;
    }

    /** @return string[] the context blocks of the model calls of a task */
    private function contextOf(string $task): array
    {
        $out = [];
        foreach ($this->fake()->sent() as $request) {
            if ($request->task !== $task) {
                continue;
            }
            foreach ($request->blocks as $block) {
                if (str_contains($block->text, '<course_brief>')) {
                    $out[] = $block->text;
                }
            }
        }

        return $out;
    }

    public function testTheApprovedProfileTravelsAsTypedDataAndTheStyleGuideNeverDoes(): void
    {
        $this->approvedFromGuide();
        $author = $this->author();
        $this->toOutline($author);

        $contexts = $this->contextOf('outline');
        $this->assertNotEmpty($contexts);
        $text = $contexts[0];
        $this->assertSame(1, substr_count($text, '<style_profile>'));
        preg_match('/<style_profile>(.*?)<\/style_profile>/s', $text, $m);
        $style = json_decode($m[1], true);
        $this->assertStringContainsString('advisory', $style['instruction']);
        $this->assertSame('you', $style['voice']['person']);
        $this->assertSame(['simply', 'just', 'obviously'], $style['banned']);
        $this->assertSame('learner', $style['terminology'][0]['use']);
        $this->assertSame(['student', 'user'], $style['terminology'][0]['avoid']);
        $this->assertArrayNotHasKey('theme', $style, 'colours and fonts shape the site, not the sentences');
        $this->assertStringNotContainsString('citations', $m[1]);
        $this->assertStringNotContainsString('frg_', $m[1]);
        $this->assertStringNotContainsString('styleProfileId', $text);

        // nothing of the guide's own text, in any block or system prompt of any call
        foreach ($this->fake()->sent() as $request) {
            if ($request->task === 'style_profile_extract') {
                continue; // the one call that reads the guide, behind the untrusted wrapper
            }
            $all = $request->system . implode("\n", array_map(fn ($b) => $b->text, $request->blocks));
            $this->assertStringNotContainsString('How we write for learners', $all);
            $this->assertStringNotContainsString('Northwind Academy Style Guide', $all);
            $this->assertStringNotContainsString('Source Serif and Inter', $all);
        }
    }

    public function testLessonCallsCarryTheProfileToo(): void
    {
        $this->manual('Plain', ['voice' => ['person' => 'neutral', 'citations' => []]]);
        $this->toApplyReview($this->author());

        $contexts = $this->contextOf('lesson');
        $this->assertNotEmpty($contexts);
        foreach ($contexts as $context) {
            $this->assertStringContainsString('"person":"neutral"', $context);
        }
    }

    public function testWithoutAnApprovedProfileThePromptIsUnchanged(): void
    {
        $this->toOutline($this->author());
        foreach ($this->contextOf('outline') as $context) {
            $this->assertStringNotContainsString('<style_profile>', $context);
        }
        // a suggestion is not used
        $this->actingAs($this->author(), 'api')->postJson(self::P, ['name' => 'Draft', 'profile' => ['voice' => ['person' => 'we', 'citations' => []]]])->assertCreated();
        $this->toOutline($this->author());
        foreach ($this->contextOf('outline') as $context) {
            $this->assertStringNotContainsString('<style_profile>', $context);
        }
    }

    public function testAThemeOnlyProfileAddsNothingToThePrompt(): void
    {
        $this->manual('Colours', ['theme' => ['preset' => 'coffee', 'citations' => []]]);
        $this->toOutline($this->author());
        foreach ($this->contextOf('outline') as $context) {
            $this->assertStringNotContainsString('<style_profile>', $context);
        }
    }

    public function testTwoCoursesFromOneSourceReadDifferentlyForTwoProfiles(): void
    {
        $this->manual('Academy', ['voice' => ['person' => 'you', 'formality' => 2, 'citations' => []]]);
        $named = $this->manual('Expert track', ['voice' => ['person' => 'we', 'formality' => 5, 'citations' => []]], ['scopeType' => 'brief']);
        $this->assertSame('approved', StyleProfile::query()->findOrFail($named)->status);
        $this->assertSame('Academy', StyleProfile::current()?->name, 'a named profile does not replace the academy default');

        $author = $this->author();
        $first = $this->toOutline($author);
        $second = $this->uploaded($author);
        $this->actingAs($author, 'api')->putJson("/api/admin/course-builder/sessions/{$second->id}/brief", ['styleProfileId' => $named])->assertOk()->assertJsonPath('data.brief.styleProfileId', $named);
        $this->action($author, $second, 'answer', 'interview', ['key' => 'duration', 'value' => ['totalMinutes' => 30, 'lessonMinutes' => 10]])->assertStatus(202);
        $this->action($author, $second, 'answer', 'interview', ['key' => 'assessments', 'value' => ['quiz']])->assertStatus(202);
        $this->action($author, $second, 'decide_for_me', 'interview')->assertStatus(202);

        $contexts = $this->contextOf('outline');
        $this->assertCount(2, $contexts);
        $this->assertStringContainsString('"person":"you"', $contexts[0]);
        $this->assertStringContainsString('"formality":2', $contexts[0]);
        $this->assertStringContainsString('"person":"we"', $contexts[1]);
        $this->assertStringContainsString('"formality":5', $contexts[1]);
        $this->assertNotSame($contexts[0], $contexts[1]);
        $this->assertNotNull($first);
    }

    public function testBriefAcceptsOnlyAnApprovedProfileAndNullClearsIt(): void
    {
        $author = $this->author();
        $session = $this->newSession($author);
        $uri = "/api/admin/course-builder/sessions/{$session->id}/brief";
        $draft = $this->actingAs($author, 'api')->postJson(self::P, ['name' => 'Draft', 'profile' => ['voice' => ['person' => 'we', 'citations' => []]]])->json('data.id');
        $approved = $this->manual('Approved', ['voice' => ['person' => 'you', 'citations' => []]]);

        $this->actingAs($author, 'api')->putJson($uri, ['styleProfileId' => $draft])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'approved'));
        $this->actingAs($author, 'api')->putJson($uri, ['styleProfileId' => '01aaaaaaaaaaaaaaaaaaaaaaaa'])->assertStatus(422);
        $this->actingAs($author, 'api')->putJson($uri, ['styleProfileId' => 'not an id'])->assertStatus(422);
        $this->actingAs($author, 'api')->putJson($uri, ['styleProfileId' => $approved])->assertOk();
        $this->assertSame($approved, $session->refresh()->brief['styleProfileId']);
        $rows = $this->actingAs($author, 'api')->getJson($uri)->assertOk()->json('data.rows');
        $this->assertSame('Approved', collect($rows)->firstWhere('key', 'style')['value']);

        $this->actingAs($author, 'api')->putJson($uri, ['styleProfileId' => null])->assertOk();
        $this->assertNull($session->refresh()->brief['styleProfileId']);
        $this->assertSame('Academy default: Approved', collect($this->actingAs($author, 'api')->getJson($uri)->json('data.rows'))->firstWhere('key', 'style')['value']);
    }

    public function testAV3BriefStaysValidAndAProfileThatIsNoLongerApprovedFallsBackToTheDefault(): void
    {
        $schemas = app(SchemaRegistry::class);
        $v3 = (new BriefService($schemas))->defaults($this->newSession($this->author()));
        $v3['pricing'] = ['mode' => 'free'];
        $this->assertSame([], $schemas->validate('course-brief/v3', $v3));
        $this->assertSame([], $schemas->validate('course-brief/v4', $v3));
        $this->assertSame([], $schemas->validate('course-brief/v4', $v3 + ['styleProfileId' => null]));
        $this->assertNotSame([], $schemas->validate('course-brief/v4', $v3 + ['styleProfileId' => 'short']));

        $default = $this->manual('Default', ['voice' => ['person' => 'you', 'citations' => []]]);
        $named = $this->manual('Named', ['voice' => ['person' => 'we', 'citations' => []]], ['scopeType' => 'brief']);
        $revision = $this->actingAs($this->author(), 'api')->postJson(self::P, ['from' => $named, 'profile' => ['voice' => ['person' => 'neutral', 'citations' => []]]])->json('data.id');
        $this->actingAs($this->admin(), 'api')->postJson(self::P . "/{$revision}/approve")->assertOk();
        $this->assertSame('superseded', StyleProfile::query()->findOrFail($named)->status);

        $session = $this->newSession($this->author());
        $session->brief = $v3 + ['styleProfileId' => $named];
        $this->assertSame($default, app(\Ulams\CourseBuilder\StyleProfile\StyleProfileResolver::class)->profileFor($session)?->id);
        $session->brief = $v3 + ['styleProfileId' => $revision];
        $this->assertSame($revision, app(\Ulams\CourseBuilder\StyleProfile\StyleProfileResolver::class)->profileFor($session)?->id);
    }

    public function testEntryPointScopeIsRefusedUntilEntryPointsExist(): void
    {
        $this->actingAs($this->author(), 'api')->postJson(self::P, ['name' => 'EP', 'scopeType' => 'entry_point', 'scopeId' => 'sales', 'profile' => ['voice' => ['person' => 'we']]])->assertStatus(422);
        $this->actingAs($this->author(), 'api')->postJson(self::P, ['name' => '', 'scopeType' => 'brief', 'profile' => ['voice' => ['person' => 'we']]])->assertStatus(422);
        $this->actingAs($this->author(), 'api')->postJson(self::P, ['name' => 'Bad', 'scopeType' => 'brief', 'scopeId' => 'Has Space', 'profile' => ['voice' => ['person' => 'we']]])->assertStatus(422);
    }
}
