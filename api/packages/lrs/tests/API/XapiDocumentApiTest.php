<?php

namespace Ulams\Lrs\Tests\API;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Ulams\Lrs\Models\State;
use Ulams\Lrs\Tests\TestCase;
use Ulams\Lrs\Tests\Traits\XapiTesting;

class XapiDocumentApiTest extends TestCase
{
    use DatabaseTransactions, XapiTesting;

    private string $agent;
    private string $activityId = 'https://example.com/activities/au-1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXapi();
        $this->agent = json_encode(['objectType' => 'Agent', 'account' => ['homePage' => 'https://ulams.app', 'name' => 'jane@example.com']]);
    }

    private function stateQuery(array $extra = []): string
    {
        return '/activities/state?' . http_build_query(array_merge([
            'activityId' => $this->activityId,
            'agent' => $this->agent,
        ], $extra));
    }

    public function testStatePutGetAndEtag(): void
    {
        $registration = (string) Str::uuid();
        $query = $this->stateQuery(['stateId' => 'LMS.LaunchData', 'registration' => $registration]);

        $this->xapi('PUT', $query, ['launchMode' => 'Normal'])->assertNoContent();

        $response = $this->xapi('GET', $query)->assertOk();
        $this->assertSame(['launchMode' => 'Normal'], $response->json());
        $this->assertStringStartsWith('application/json', $response->headers->get('Content-Type'));
        $etag = $response->headers->get('ETag');
        $this->assertNotEmpty($etag);

        // A different registration is a different document.
        $this->xapi('GET', $this->stateQuery(['stateId' => 'LMS.LaunchData']))->assertNotFound();

        $record = State::query()->where('registration', $registration)->firstOrFail();
        $this->assertSame('account::jane@example.com@https://ulams.app', $record->vid);
        $this->assertSame($this->access->ownerId(), $record->owner_id);

        // Concurrency.
        $this->xapi('PUT', $query, ['launchMode' => 'Review'], ['If-Match' => '"stale"'])->assertStatus(412);
        $this->xapi('PUT', $query, ['launchMode' => 'Review'], ['If-None-Match' => '*'])->assertStatus(412);
        $this->xapi('PUT', $query, ['launchMode' => 'Review'], ['If-Match' => $etag])->assertNoContent();
        $this->assertSame(['launchMode' => 'Review'], $this->xapi('GET', $query)->json());
    }

    public function testStatePostMergesJson(): void
    {
        $query = $this->stateQuery(['stateId' => 'bookmark']);

        $this->xapi('POST', $query, ['a' => 1, 'b' => 1])->assertNoContent();
        $this->xapi('POST', $query, ['b' => 2, 'c' => 3])->assertNoContent();

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $this->xapi('GET', $query)->json());

        $this->xapi('POST', $query, 'plain', ['Content-Type' => 'text/plain'])->assertStatus(400);
    }

    public function testNonJsonStateIsStoredAsIs(): void
    {
        $query = $this->stateQuery(['stateId' => 'suspend']);

        $this->xapi('PUT', $query, 'page=3', ['Content-Type' => 'text/plain'])->assertNoContent();

        $response = $this->xapi('GET', $query)->assertOk();
        $this->assertSame('page=3', $response->getContent());
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
    }

    public function testStateListAndDelete(): void
    {
        $this->xapi('PUT', $this->stateQuery(['stateId' => 'one']), ['x' => 1])->assertNoContent();
        $this->xapi('PUT', $this->stateQuery(['stateId' => 'two']), ['x' => 2])->assertNoContent();

        $this->assertSame(['one', 'two'], $this->xapi('GET', $this->stateQuery())->json());
        $this->assertSame([], $this->xapi('GET', $this->stateQuery(['since' => now()->addHour()->toIso8601String()]))->json());

        $this->xapi('DELETE', $this->stateQuery(['stateId' => 'one']))->assertNoContent();
        $this->assertSame(['two'], $this->xapi('GET', $this->stateQuery())->json());

        $this->xapi('DELETE', $this->stateQuery())->assertNoContent();
        $this->assertSame([], $this->xapi('GET', $this->stateQuery())->json());
    }

    public function testStateParametersAreValidated(): void
    {
        $this->xapi('GET', '/activities/state?stateId=x&agent=' . urlencode($this->agent))->assertStatus(400);
        $this->xapi('GET', '/activities/state?stateId=x&activityId=' . urlencode($this->activityId))->assertStatus(400);
        $this->xapi('PUT', $this->stateQuery(), ['x' => 1])->assertStatus(400);
        $this->xapi('PUT', $this->stateQuery(['stateId' => 'x', 'registration' => 'nope']), ['x' => 1])->assertStatus(400);
        $this->xapi('PUT', $this->stateQuery(['stateId' => 'x']), '{bad json')->assertStatus(400);
    }

    public function testAgentProfile(): void
    {
        $query = '/agents/profile?' . http_build_query(['agent' => $this->agent, 'profileId' => 'cmi5LearnerPreferences']);

        $this->xapi('PUT', $query, ['languagePreference' => 'pl-PL'])->assertNoContent();
        $this->assertSame(['languagePreference' => 'pl-PL'], $this->xapi('GET', $query)->json());
        $this->assertSame(['cmi5LearnerPreferences'], $this->xapi('GET', '/agents/profile?agent=' . urlencode($this->agent))->json());

        $this->xapi('DELETE', $query)->assertNoContent();
        $this->xapi('GET', $query)->assertNotFound();
        $this->xapi('DELETE', '/agents/profile?agent=' . urlencode($this->agent))->assertStatus(400);
    }

    public function testActivityProfile(): void
    {
        $query = '/activities/profile?' . http_build_query(['activityId' => $this->activityId, 'profileId' => 'settings']);

        $this->xapi('POST', $query, ['a' => 1])->assertNoContent();
        $this->xapi('POST', $query, ['b' => 2])->assertNoContent();
        $this->assertSame(['a' => 1, 'b' => 2], $this->xapi('GET', $query)->json());

        $this->xapi('DELETE', $query)->assertNoContent();
        $this->xapi('GET', $query)->assertNotFound();
    }

    public function testDocumentsNeedAValidToken(): void
    {
        $this->xapi('GET', $this->stateQuery(['stateId' => 'x']), null, ['Authorization' => 'Basic invalid'])->assertUnauthorized();
    }
}
