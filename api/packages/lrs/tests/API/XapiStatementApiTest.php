<?php

namespace Ulams\Lrs\Tests\API;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Ulams\Lrs\Database\Seeders\LrsSeeder;
use Ulams\Lrs\Models\Access;
use Ulams\Lrs\Models\Owner;
use Ulams\Lrs\Models\Statement;
use Ulams\Lrs\Tests\TestCase;
use Ulams\Lrs\Tests\Traits\XapiTesting;
use Ulams\Lrs\Xapi\StatementValidator;

class XapiStatementApiTest extends TestCase
{
    use DatabaseTransactions, XapiTesting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXapi();
    }

    public function testPostStoresAStatementAndReturnsItsId(): void
    {
        $response = $this->xapi('POST', '/statements', $this->statement());

        $response->assertOk()->assertHeader('X-Experience-API-Version', '1.0.3');
        $ids = $response->json();
        $this->assertCount(1, $ids);
        $this->assertTrue(StatementValidator::isUuid($ids[0]));

        $record = Statement::query()->where('uuid', $ids[0])->firstOrFail();
        $this->assertSame($this->access->getKey(), $record->access_id);
        $this->assertSame($this->access->client_id, $record->client_id);
        $this->assertSame($this->access->ownerId(), $record->owner_id);
        $this->assertSame(Statement::VALIDATION_PASSED, $record->validation);
        $this->assertSame('1.0.0', $record->data->version);
        $this->assertNotEmpty($record->data->stored);
        $this->assertSame($record->data->stored, $record->data->timestamp);
        $this->assertSame($this->access->uuid, $record->data->authority->account->name);
    }

    public function testPostAcceptsABatch(): void
    {
        $id = (string) Str::uuid();
        $response = $this->xapi('POST', '/statements', [$this->statement(['id' => $id]), $this->statement()]);

        $response->assertOk();
        $this->assertCount(2, $response->json());
        $this->assertSame($id, $response->json()[0]);
    }

    public function testInvalidStatementIsRejectedAndNothingIsStored(): void
    {
        $before = Statement::query()->count();

        $this->xapi('POST', '/statements', [$this->statement(), ['verb' => ['id' => 'x']]])
            ->assertStatus(400);

        $this->assertSame($before, Statement::query()->count());
    }

    public function testRequiresTheVersionHeader(): void
    {
        $this->xapi('POST', '/statements', $this->statement(), ['X-Experience-API-Version' => null])->assertStatus(400);
        $this->xapi('GET', '/statements', null, ['X-Experience-API-Version' => '0.95'])->assertStatus(400);
    }

    public function testUnknownOrInactiveAccessIsUnauthorized(): void
    {
        $this->xapi('GET', '/statements', null, [], '/trax/api/' . Str::uuid() . '/xapi/std')->assertUnauthorized();

        $this->access->update(['active' => false]);
        $this->xapi('GET', '/statements')->assertUnauthorized();
    }

    public function testPutAndGetById(): void
    {
        $id = (string) Str::uuid();

        $this->xapi('PUT', '/statements?statementId=' . $id, $this->statement())->assertNoContent();

        $this->xapi('GET', '/statements?statementId=' . $id)
            ->assertOk()
            ->assertJsonPath('id', $id)
            ->assertJsonPath('verb.id', 'http://adlnet.gov/expapi/verbs/initialized');

        $this->xapi('GET', '/statements?statementId=' . Str::uuid())->assertNotFound();
        $this->xapi('PUT', '/statements', $this->statement())->assertStatus(400);
        $this->xapi('PUT', '/statements?statementId=' . $id, $this->statement(['id' => (string) Str::uuid()]))->assertStatus(400);
    }

    public function testSameIdIsIdempotentButConflictsWithDifferentContent(): void
    {
        $id = (string) Str::uuid();

        $this->xapi('PUT', '/statements?statementId=' . $id, $this->statement())->assertNoContent();
        $this->xapi('PUT', '/statements?statementId=' . $id, $this->statement())->assertNoContent();
        $this->assertSame(1, Statement::query()->where('uuid', $id)->count());

        $this->xapi('POST', '/statements', $this->statement(['id' => $id, 'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed']]))
            ->assertStatus(409);
    }

    public function testVoiding(): void
    {
        $id = (string) Str::uuid();
        $this->xapi('PUT', '/statements?statementId=' . $id, $this->statement())->assertNoContent();

        $this->xapi('POST', '/statements', $this->statement([
            'verb' => ['id' => StatementValidator::VOIDED_VERB],
            'object' => ['objectType' => 'StatementRef', 'id' => $id],
        ]))->assertOk();

        $this->xapi('GET', '/statements?statementId=' . $id)->assertNotFound();
        $this->xapi('GET', '/statements?voidedStatementId=' . $id)->assertOk()->assertJsonPath('id', $id);
        $this->assertCount(1, $this->xapi('GET', '/statements')->json('statements'));
    }

    public function testQueryFilters(): void
    {
        $registration = (string) Str::uuid();
        $jane = ['objectType' => 'Agent', 'account' => ['homePage' => 'https://ulams.app', 'name' => 'jane@example.com']];

        $this->xapi('POST', '/statements', [
            $this->statement(['actor' => $jane, 'context' => ['registration' => $registration]]),
            $this->statement(['actor' => $jane, 'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed']]),
            $this->statement(['object' => ['id' => 'https://example.com/activities/other']]),
        ])->assertOk();

        $count = fn (array $query) => count($this->xapi('GET', '/statements?' . http_build_query($query))->assertOk()->json('statements'));

        $this->assertSame(3, $count([]));
        $this->assertSame(2, $count(['agent' => json_encode($jane)]));
        $this->assertSame(1, $count(['verb' => 'http://adlnet.gov/expapi/verbs/completed']));
        $this->assertSame(1, $count(['activity' => 'https://example.com/activities/other']));
        $this->assertSame(1, $count(['registration' => $registration]));
        $this->assertSame(0, $count(['since' => now()->addHour()->toIso8601String()]));
        $this->assertSame(3, $count(['until' => now()->addHour()->toIso8601String()]));

        $this->xapi('GET', '/statements?agent=nope')->assertStatus(400);
        $this->xapi('GET', '/statements?registration=nope')->assertStatus(400);
    }

    public function testPagingWithMore(): void
    {
        $batch = array_map(fn () => $this->statement(), range(1, 5));
        $this->xapi('POST', '/statements', $batch)->assertOk();

        $first = $this->xapi('GET', '/statements?limit=2')->assertOk();
        $this->assertCount(2, $first->json('statements'));
        $more = $first->json('more');
        $this->assertNotEmpty($more);

        $seen = array_column($first->json('statements'), 'id');
        while ($more) {
            $page = $this->xapi('GET', '/statements?' . parse_url($more, PHP_URL_QUERY))->assertOk();
            $seen = [...$seen, ...array_column($page->json('statements'), 'id')];
            $more = $page->json('more');
        }

        $this->assertCount(5, array_unique($seen));
    }

    public function testStoresAreIsolatedByOwner(): void
    {
        $this->xapi('POST', '/statements', $this->statement())->assertOk();

        // A second store with its own owner, client and access.
        $owner = Owner::create(['name' => 'Other']);
        $this->access->client->update(['owner_id' => $owner->id]);
        $this->assertSame(0, count($this->xapi('GET', '/statements')->json('statements')));
    }

    public function testAboutIsPublic(): void
    {
        $this->getJson($this->endpoint . '/about')->assertOk()->assertJson(['version' => ['1.0.3']]);
    }

    public function testAttachmentsAreNotSupported(): void
    {
        $this->xapi('POST', '/statements', '--boundary', ['Content-Type' => 'multipart/mixed; boundary=boundary'])
            ->assertStatus(400);
    }
}
