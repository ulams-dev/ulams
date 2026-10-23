<?php

namespace Ulams\CourseBuilder\Tests\Feature;

use Ulams\CourseBuilder\Models\StyleProfile;
use Ulams\CourseBuilder\Tests\TestCase;

/**
 * Style profile endpoints (ADR 0196): a student is refused, a guest is unauthenticated, unknown ids are 404, another
 * tutor cannot edit or delete a suggestion, and only an admin approves. Tenant isolation comes from the database
 * per tenant: an id of another tenant is an unknown id here (the cross-tenant HTTP check is in the tenancy package).
 */
class StyleProfileIsolationTest extends TestCase
{
    private const P = '/api/admin/course-builder/style-profiles';
    private const MISSING = '01aaaaaaaaaaaaaaaaaaaaaaaa';

    /** @return array<int,array{0:string,1:string}> */
    private function endpoints(string $id): array
    {
        return [
            ['GET', self::P],
            ['POST', self::P],
            ['GET', self::P . "/{$id}"],
            ['PATCH', self::P . "/{$id}"],
            ['POST', self::P . "/{$id}/approve"],
            ['DELETE', self::P . "/{$id}"],
        ];
    }

    private function profile(): string
    {
        return $this->actingAs($this->author(), 'api')->postJson(self::P, ['name' => 'Mine', 'profile' => ['voice' => ['person' => 'you', 'citations' => []]]])->assertCreated()->json('data.id');
    }

    public function testStudentsAreForbiddenAndGuestsAreUnauthenticatedOnEveryEndpoint(): void
    {
        $id = $this->profile();
        foreach ($this->endpoints($id) as [$method, $uri]) {
            $this->actingAs($this->student(), 'api')->json($method, $uri)->assertStatus(403);
        }
        $this->app['auth']->forgetGuards();
        foreach ($this->endpoints($id) as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(401);
        }
    }

    public function testAnotherTutorCanReadButNotEditDeleteOrApprove(): void
    {
        $id = $this->profile();
        $other = $this->tutor();

        $this->actingAs($other, 'api')->getJson(self::P)->assertOk();
        $this->actingAs($other, 'api')->getJson(self::P . "/{$id}")->assertOk();
        $this->actingAs($other, 'api')->patchJson(self::P . "/{$id}", ['name' => 'Hijack'])->assertForbidden();
        $this->actingAs($other, 'api')->deleteJson(self::P . "/{$id}")->assertForbidden();
        $this->actingAs($other, 'api')->postJson(self::P . "/{$id}/approve")->assertForbidden();
        $this->assertSame('Mine', StyleProfile::query()->findOrFail($id)->name);
        $this->assertSame('suggested', StyleProfile::query()->findOrFail($id)->status);
    }

    public function testAnAdminMayEditDeleteAndApproveASuggestion(): void
    {
        $id = $this->profile();
        $admin = $this->admin();
        $this->actingAs($admin, 'api')->patchJson(self::P . "/{$id}", ['name' => 'Renamed'])->assertOk();
        $this->actingAs($admin, 'api')->postJson(self::P . "/{$id}/approve")->assertOk();
        $this->actingAs($admin, 'api')->deleteJson(self::P . "/{$id}")->assertStatus(409);
    }

    public function testUnknownIdsAreNotFound(): void
    {
        $author = $this->author();
        foreach ([
            ['GET', self::P . '/' . self::MISSING],
            ['GET', self::P . '/not-an-id'],
            ['PATCH', self::P . '/' . self::MISSING],
            ['POST', self::P . '/' . self::MISSING . '/approve'],
            ['DELETE', self::P . '/' . self::MISSING],
        ] as [$method, $uri]) {
            $this->actingAs($this->admin(), 'api')->json($method, $uri)->assertStatus(404);
            $this->actingAs($author, 'api')->json($method, $uri)->assertStatus(404);
        }
        $this->actingAs($author, 'api')->postJson(self::P, ['from' => self::MISSING, 'profile' => ['voice' => ['person' => 'you']]])->assertStatus(404);
    }

    public function testStyleGuideFragmentsAreNotReadableThroughAnotherAuthorsFragmentEndpoint(): void
    {
        $owner = $this->author();
        $data = $this->actingAs($owner, 'api')->post(self::P, ['file' => $this->fixture('style-guide.md')], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $fragment = array_key_first($this->actingAs($owner, 'api')->getJson(self::P . '/' . $data['id'])->json('data.fragments'));

        $this->actingAs($owner, 'api')->getJson("/api/admin/course-builder/fragments/{$fragment}")->assertOk();
        $this->actingAs($this->tutor(), 'api')->getJson("/api/admin/course-builder/fragments/{$fragment}")->assertForbidden();
        // the cited passages travel with the profile for reviewers who did not upload the guide
        $this->actingAs($this->tutor(), 'api')->getJson(self::P . '/' . $data['id'])->assertOk()->assertJsonPath("data.fragments.{$fragment}.label", fn ($v) => is_string($v));
    }
}
