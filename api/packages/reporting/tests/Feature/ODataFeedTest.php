<?php

namespace Ulams\Reporting\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\Core\Tests\Concerns\ForeignTenantToken;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Reporting\Models\SavedReport;
use Ulams\Reporting\Tests\FeedTestCase;

/** The read-only OData v4 feed of a saved report (RB-6): protocol, auth, limits, suppression, the export log. */
class ODataFeedTest extends FeedTestCase
{
    use CreatesUsers;
    use ForeignTenantToken;

    private const ROOT = '/api/reporting/odata';

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerLearnerDatasets();
    }

    private function report($owner, array $definition, array $extra = []): SavedReport
    {
        return SavedReport::query()->create($extra + ['name' => 'Feed', 'owner_user_id' => $owner->getKey(), 'visibility' => 'private', 'feed_enabled' => true, 'definition' => $definition]);
    }

    private function rowsReport($owner, array $extra = []): SavedReport
    {
        return $this->report($owner, ['dataset' => 'learner_calls', 'columns' => ['task', 'user_id', 'cost_usd', 'cached', 'created_at'], 'sort' => [['column' => 'user_id']]], $extra);
    }

    /** @return array{0:string,1:string} the secret and the token id of a scoped token */
    private function token($user, array $scopes = ['reports:read'], ?array $resources = null): array
    {
        $issued = app(PersonalAccessTokenServiceContract::class)->issue($user, 'bi', $scopes, 30, 'integration', 'bi', 'admin', null, null, $resources);

        return [$issued->secret, $issued->token->getKey()];
    }

    private function bearer(string $secret): array
    {
        return ['Authorization' => 'Bearer ' . $secret, 'Accept' => 'application/json'];
    }

    private function basic(string $secret, string $user = 'anything'): array
    {
        return ['Authorization' => 'Basic ' . base64_encode("{$user}:{$secret}"), 'Accept' => 'application/json'];
    }

    private function feed(string $uri, array $headers)
    {
        $response = $this->getJson($uri, $headers);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    private function entity(SavedReport $report): string
    {
        return self::ROOT . '/Report_' . $report->id;
    }

    // ---- authentication ----

    public function testAFeedNeedsCredentialsAndAsksForThemTheWayExcelExpects(): void
    {
        $this->feed(self::ROOT, [])->assertUnauthorized()->assertHeader('OData-Version', '4.0');
        $challenge = $this->feed(self::ROOT . '/$metadata', [])->assertUnauthorized()->headers->get('WWW-Authenticate');
        $this->assertStringStartsWith('Basic realm="ULAMS reports', $challenge);
        $this->feed(self::ROOT, $this->basic('ulams_pat_nonsense'))->assertUnauthorized();
        $this->feed(self::ROOT, $this->basic('not-a-token-at-all'))->assertUnauthorized();
        $this->feed(self::ROOT, ['Authorization' => 'Basic ' . base64_encode('no-colon')])->assertUnauthorized();
    }

    public function testBearerAndBasicCredentialsOpenTheSameFeedAndTheUserNameIsIgnored(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->rowsReport($admin);
        $this->calls('a', [1]);
        [$secret] = $this->token($admin);

        foreach ([$this->bearer($secret), $this->basic($secret), $this->basic($secret, ''), $this->basic($secret, 'ada@acme.test')] as $headers) {
            $this->feed($this->entity($report), $headers)->assertOk()->assertJsonPath('value.0.task', 'a');
        }
    }

    public function testASessionOrATokenWithoutReportsReadDoesNotOpenAFeed(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->rowsReport($admin);
        [$noReports] = $this->token($admin, ['courses:read']);
        [$writeOnly] = $this->token($admin, ['reports:write']);

        $this->feed($this->entity($report), $this->bearer($noReports))->assertForbidden()->assertJsonPath('error', 'scope_missing');
        $this->feed($this->entity($report), $this->bearer($writeOnly))->assertOk();
    }

    // ---- service document and metadata ----

    public function testTheServiceDocumentListsTheFeedsOfTheTokensUser(): void
    {
        $builder = $this->makeInstructor();
        $builder->givePermissionTo(['reporting_create', 'reporting_ai_usage']);
        $on = $this->rowsReport($builder);
        $this->rowsReport($builder, ['feed_enabled' => false]);
        $other = $this->makeAdmin();
        $this->rowsReport($other);
        $admin = $builder;
        [$secret] = $this->token($admin);
        $manager = $this->makeAdmin();
        [$managerSecret] = $this->token($manager);

        $doc = $this->feed(self::ROOT, $this->bearer($secret))->assertOk()->assertHeader('OData-Version', '4.0')->json();

        $this->assertSame(url(self::ROOT) . '/$metadata', $doc['@odata.context']);
        $this->assertSame([['name' => 'Report_' . $on->id, 'kind' => 'EntitySet', 'url' => 'Report_' . $on->id]], $doc['value']);
        $this->assertCount(2, $this->feed(self::ROOT, $this->bearer($managerSecret))->json('value'), 'a manager of reports sees every feed that is on');
        $this->assertStringContainsString('odata.metadata=minimal', $this->feed(self::ROOT, $this->bearer($secret))->headers->get('Content-Type'));
    }

    public function testMetadataIsAWellFormedCsdlWithTypedPropertiesAndCapabilities(): void
    {
        $admin = $this->makeAdmin();
        $rows = $this->rowsReport($admin);
        $grouped = $this->report($admin, ['dataset' => 'learner_calls', 'groupBy' => ['task', ['column' => 'created_at', 'bucket' => 'month']], 'aggregates' => [['fn' => 'count_distinct', 'column' => 'user_id', 'as' => 'learners'], ['fn' => 'sum', 'column' => 'cost_usd', 'as' => 'cost']]], ['name' => 'Grouped']);
        $this->report($admin, ['dataset' => 'learner_calls', 'columns' => ['nope']], ['name' => 'Broken']);
        [$secret] = $this->token($admin);

        $xml = $this->feed(self::ROOT . '/$metadata', $this->bearer($secret))->assertOk();
        $this->assertStringContainsString('application/xml', $xml->headers->get('Content-Type'));
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml->getContent()), 'well-formed XML');
        $x = new DOMXPath($dom);
        $x->registerNamespace('edmx', 'http://docs.oasis-open.org/odata/ns/edmx');
        $x->registerNamespace('e', 'http://docs.oasis-open.org/odata/ns/edm');

        $this->assertSame('4.0', $x->query('/edmx:Edmx/@Version')->item(0)->nodeValue);
        $types = [];
        foreach ($x->query('//e:EntityType') as $type) {
            $types[$type->getAttribute('Name')] = $type;
        }
        $this->assertEqualsCanonicalizing(['Report_' . $rows->id . '_Row', 'Report_' . $grouped->id . '_Row'], array_keys($types), 'a report that needs repair is not described');
        $props = fn (string $name) => collect($x->query('e:Property', $types[$name]))->mapWithKeys(fn ($p) => [$p->getAttribute('Name') => $p->getAttribute('Type')])->all();
        $this->assertSame(['RowNumber' => 'Edm.Int64', 'task' => 'Edm.String', 'user_id' => 'Edm.Int64', 'cost_usd' => 'Edm.Double', 'cached' => 'Edm.Boolean', 'created_at' => 'Edm.DateTimeOffset'], $props('Report_' . $rows->id . '_Row'));
        $this->assertSame(['RowNumber' => 'Edm.Int64', 'task' => 'Edm.String', 'created_at_month' => 'Edm.Date', 'learners' => 'Edm.Int64', 'cost' => 'Edm.Double', 'Withheld' => 'Edm.Boolean'], $props('Report_' . $grouped->id . '_Row'));
        $this->assertSame('RowNumber', $x->query('e:Key/e:PropertyRef/@Name', $types['Report_' . $rows->id . '_Row'])->item(0)->nodeValue);
        $this->assertSame('false', $x->query('e:Property[@Name="RowNumber"]/@Nullable', $types['Report_' . $rows->id . '_Row'])->item(0)->nodeValue);

        $sets = $x->query('//e:EntityContainer/e:EntitySet');
        $this->assertCount(2, $sets);
        $set = $sets->item(0);
        $this->assertStringStartsWith('Ulams.Reporting.Report_', $set->getAttribute('EntityType'));
        $terms = collect($x->query('e:Annotation', $set))->mapWithKeys(fn ($a) => [$a->getAttribute('Term') => $a->getAttribute('Bool') ?: $x->query('e:Record/e:PropertyValue/@Bool', $a)->item(0)?->nodeValue])->all();
        $this->assertSame('false', $terms['Org.OData.Capabilities.V1.FilterRestrictions']);
        $this->assertSame('false', $terms['Org.OData.Capabilities.V1.SortRestrictions']);
        $this->assertSame('false', $terms['Org.OData.Capabilities.V1.ExpandRestrictions']);
        $this->assertSame('true', $terms['Org.OData.Capabilities.V1.CountRestrictions']);
        $this->assertSame('true', $terms['Org.OData.Capabilities.V1.TopSupported']);
        $this->assertSame('true', $terms['Org.OData.Capabilities.V1.SkipSupported']);
    }

    public function testMetadataOfNoFeedsIsStillAValidDocument(): void
    {
        [$secret] = $this->token($this->makeAdmin());

        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($this->feed(self::ROOT . '/$metadata', $this->bearer($secret))->assertOk()->getContent()));
        $this->assertSame(0, $dom->getElementsByTagName('EntitySet')->length);
    }

    // ---- entity sets ----

    public function testRowsComeAsODataJsonWithTypedValues(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->rowsReport($admin);
        $this->calls('outline', [7], ['cost_micro_usd' => 2500000, 'created_at' => '2026-10-20 08:30:00']);
        $this->calls('lessons', [8], ['cache_read_tokens' => 5, 'created_at' => '2026-10-21 09:00:00']);
        [$secret] = $this->token($admin);

        $response = $this->feed($this->entity($report), $this->bearer($secret))->assertOk();

        $this->assertSame(url(self::ROOT) . '/$metadata#Report_' . $report->id, $response->json()['@odata.context']);
        $this->assertArrayNotHasKey('@odata.count', $response->json());
        $this->assertArrayNotHasKey('@odata.nextLink', $response->json());
        $this->assertSame(['RowNumber' => 1, 'task' => 'outline', 'user_id' => 7, 'cost_usd' => 2.5, 'cached' => false, 'created_at' => '2026-10-20T08:30:00+00:00'], $response->json('value.0'));
        $this->assertSame(['RowNumber' => 2, 'task' => 'lessons', 'user_id' => 8, 'cost_usd' => 1, 'cached' => true, 'created_at' => '2026-10-21T09:00:00+00:00'], $response->json('value.1'));
        $this->assertStringContainsString('IEEE754Compatible=false', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function testSelectTopSkipAndCount(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->rowsReport($admin);
        $this->calls('t', range(1, 10));
        [$secret] = $this->token($admin);
        $get = fn (string $query) => $this->feed($this->entity($report) . '?' . $query, $this->bearer($secret));

        $this->assertSame([['task' => 't', 'user_id' => 1]], $get('$select=task,user_id&$top=1')->assertOk()->json('value'));
        $this->assertSame([4, 5, 6], array_column($get('$skip=3&$top=3')->json('value'), 'user_id'));
        $this->assertSame([4, 5, 6], array_column($get('$skip=3&$top=3')->json('value'), 'RowNumber'));
        $this->assertSame(10, $get('$count=true&$top=2')->json()['@odata.count'], 'the count is of the whole report, not of the page');
        $this->assertSame([], $get('$skip=50')->json('value'));
        $this->assertSame(6, count($get('$select=*&$top=5')->json('value.0')), '$select=* is every property: the row number and the five columns');
        $this->assertCount(10, $get('$format=json')->json('value'));
        $this->assertCount(10, $get('$format=application/json&custom=1')->json('value'), 'options that are not OData are ignored');
    }

    public function testLongReportsPageAtFiveThousandRowsWithANextLink(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->rowsReport($admin);
        $rows = [];
        for ($i = 1; $i <= 5003; $i++) {
            $rows[] = ['id' => (string) Str::ulid(), 'task' => 'bulk', 'driver' => 'fake', 'model_requested' => 'm', 'status' => 'ok', 'user_id' => $i, 'created_at' => now()];
        }
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('ai_calls')->insert($chunk);
        }
        [$secret] = $this->token($admin);

        $first = $this->feed($this->entity($report) . '?$select=user_id', $this->bearer($secret))->assertOk();
        $this->assertCount(5000, $first->json('value'));
        $next = $first->json()['@odata.nextLink'];
        $this->assertSame(url($this->entity($report)) . '?%24skip=5000&%24select=user_id', $next);

        $second = $this->feed(str_replace(url('/'), '/', $next), $this->bearer($secret))->assertOk();
        $this->assertSame([5001, 5002, 5003], array_column($second->json('value'), 'user_id'));
        $this->assertArrayNotHasKey('@odata.nextLink', $second->json());

        $capped = $this->feed($this->entity($report) . '?$top=5200&$select=user_id', $this->bearer($secret))->json();
        $this->assertSame(url($this->entity($report)) . '?%24skip=5000&%24top=200&%24select=user_id', $capped['@odata.nextLink'], 'the rest of $top is carried over');
        $this->assertArrayNotHasKey('@odata.nextLink', $this->feed($this->entity($report) . '?$top=5000', $this->bearer($secret))->json(), 'exactly what was asked for: no further page');
    }

    public function testUnsupportedQueryOptionsAreRefusedNotIgnored(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->rowsReport($admin);
        [$secret] = $this->token($admin);
        $get = fn (string $query) => $this->feed($this->entity($report) . '?' . $query, $this->bearer($secret));

        foreach (['$filter=task eq \'x\'', '$orderby=task', '$apply=groupby((task))', '$expand=x', '$search=x', '$compute=1 as a', '$skiptoken=abc'] as $query) {
            $response = $get($query)->assertStatus(501);
            $this->assertSame('501', $response->json('error.code'), $query);
            $this->assertStringContainsString('its filters, grouping and order are part of the report', $response->json('error.message'));
            $this->assertStringContainsString('Supported: $select, $top, $skip, $count', $response->json('error.message'));
        }
        $get('$format=xml')->assertStatus(501);
        $get('$unknown=1')->assertStatus(400);
        $get('$select=task,secret')->assertStatus(400)->assertJsonPath('error.code', '400');
        $get('$top=-1')->assertStatus(400);
        $get('$top=abc')->assertStatus(400);
        $get('$skip=1.5')->assertStatus(400);
        $get('$count=maybe')->assertStatus(400);
        $get('$top=0')->assertOk()->assertJsonPath('value', []);
    }

    public function testASmallGroupIsWithheldInTheFeedToo(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->report($admin, ['dataset' => 'learner_calls', 'groupBy' => ['task'], 'aggregates' => [['fn' => 'count_distinct', 'column' => 'user_id', 'as' => 'learners']], 'sort' => [['column' => 'task']]]);
        $this->calls('big', range(1, 12));
        $this->calls('small', range(20, 23));
        $this->calls('bigger', range(30, 50));
        [$secret] = $this->token($admin);

        $response = $this->feed($this->entity($report) . '?$count=true', $this->bearer($secret))->assertOk();

        $rows = $response->json('value');
        $this->assertSame([['RowNumber' => 1, 'task' => 'bigger', 'learners' => 21, 'Withheld' => false], ['RowNumber' => 2, 'task' => 'withheld (fewer than 10 learners)', 'learners' => null, 'Withheld' => true]], $rows);
        $this->assertStringNotContainsString('small', $response->getContent());
        $this->assertStringNotContainsString('"big"', $response->getContent());
        $this->assertSame(2, $response->json()['@odata.count']);
    }

    // ---- who may read which feed ----

    public function testAFeedIsReadOnlyWhenOnVisibleRunnableAndSound(): void
    {
        $admin = $this->makeAdmin();
        $off = $this->rowsReport($admin, ['feed_enabled' => false]);
        $broken = $this->report($admin, ['dataset' => 'learner_calls', 'columns' => ['gone']]);
        $mine = $this->rowsReport($admin);
        $role = Role::findOrCreate('partner', 'api');
        $role->givePermissionTo(Permission::findOrCreate('reporting_read', 'api'));
        $partner = config('auth.providers.users.model')::factory()->create();
        $partner->assignRole('partner');
        [$adminSecret] = $this->token($admin);
        [$partnerSecret] = $this->token($partner);

        $this->feed($this->entity($off), $this->bearer($adminSecret))->assertNotFound()->assertJsonPath('error.code', '404');
        $this->feed($this->entity($broken), $this->bearer($adminSecret))->assertStatus(409);
        $this->feed(self::ROOT . '/Report_' . str_repeat('0', 26), $this->bearer($adminSecret))->assertNotFound();
        $this->feed(self::ROOT . '/Report_short', $this->bearer($adminSecret))->assertNotFound()->assertJsonPath('error.code', '404');
        $this->feed(self::ROOT . '/Other_' . $off->id, $this->bearer($adminSecret))->assertNotFound();
        $this->feed($this->entity($mine), $this->bearer($partnerSecret))->assertNotFound();
        $mine->update(['visibility' => 'roles', 'shared_roles' => ['partner']]);
        $this->feed($this->entity($mine), $this->bearer($partnerSecret))->assertForbidden()->assertJsonPath('error.code', '403');
        $role->givePermissionTo(Permission::findOrCreate('reporting_ai_usage', 'api'));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->feed($this->entity($mine), $this->bearer($partnerSecret))->assertOk();
        $this->feed(self::ROOT, $this->bearer($partnerSecret))->assertJsonCount(1, 'value');
    }

    public function testTheFeedRunsWithTheScopeOfTheTokensUser(): void
    {
        $admin = $this->makeAdmin();
        $mine = $this->report($admin, ['dataset' => 'my_calls', 'columns' => ['task', 'user_id'], 'sort' => [['column' => 'task']]], ['visibility' => 'roles', 'shared_roles' => ['partner']]);
        $role = Role::findOrCreate('partner', 'api');
        $role->givePermissionTo(Permission::findOrCreate('reporting_read', 'api'));
        $partner = config('auth.providers.users.model')::factory()->create();
        $partner->assignRole('partner');
        $this->calls('partner-row', [$partner->getKey()]);
        $this->calls('admin-row', [$admin->getKey()]);
        [$adminSecret] = $this->token($admin);
        [$partnerSecret] = $this->token($partner);

        $this->assertSame(['admin-row', 'partner-row'], array_column($this->feed($this->entity($mine), $this->bearer($adminSecret))->json('value'), 'task'));
        $this->assertSame(['partner-row'], array_column($this->feed($this->entity($mine), $this->bearer($partnerSecret))->json('value'), 'task'));
    }

    // ---- tokens limited to one report ----

    public function testATokenLimitedToOneReportReadsThatReportAndNothingElse(): void
    {
        $admin = $this->makeAdmin();
        $one = $this->rowsReport($admin, ['name' => 'One']);
        $two = $this->rowsReport($admin, ['name' => 'Two']);
        $this->calls('x', [1]);
        [$limited, $tokenId] = $this->token($admin, ['reports:read'], [$one->id]);
        [$unlimited] = $this->token($admin);

        $this->feed($this->entity($one), $this->bearer($limited))->assertOk();
        $this->feed($this->entity($one), $this->basic($limited))->assertOk();
        $this->feed($this->entity($two), $this->bearer($limited))->assertForbidden()->assertJsonPath('error', 'resource_limited');
        $this->feed($this->entity($two), $this->bearer($unlimited))->assertOk();

        $this->assertSame(['Report_' . $one->id], array_column($this->feed(self::ROOT, $this->bearer($limited))->assertOk()->json('value'), 'name'), 'the service document lists only the report of the token');
        $this->assertCount(2, $this->feed(self::ROOT, $this->bearer($unlimited))->json('value'));
        $xml = $this->feed(self::ROOT . '/$metadata', $this->bearer($limited))->assertOk()->getContent();
        $this->assertStringContainsString('Report_' . $one->id . '_Row', $xml);
        $this->assertStringNotContainsString('Report_' . $two->id, $xml);

        // the builder's other endpoints are closed to it, the two that name its report are open
        $this->feed('/api/admin/reporting/datasets', $this->bearer($limited))->assertForbidden()->assertJsonPath('error', 'resource_limited');
        $this->feed('/api/admin/reporting/saved', $this->bearer($limited))->assertForbidden();
        $this->feed("/api/admin/reporting/saved/{$one->id}", $this->bearer($limited))->assertOk();
        $this->feed("/api/admin/reporting/saved/{$one->id}/run?format=csv", $this->bearer($limited))->assertOk();
        $this->feed("/api/admin/reporting/saved/{$two->id}/run", $this->bearer($limited))->assertForbidden()->assertJsonPath('error', 'resource_limited');
        $this->feed("/api/admin/reporting/saved/{$two->id}", $this->bearer($limited))->assertForbidden();
        $this->feed('/api/profile/me', $this->bearer($limited))->assertOk(); // identity endpoints are open to every token
        $this->app['auth']->forgetGuards();
        $this->putJson("/api/admin/reporting/saved/{$one->id}", ['name' => 'Changed'], $this->bearer($limited))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/admin/reporting/query', ['dataset' => 'learner_calls', 'columns' => ['task']], $this->bearer($limited))->assertForbidden();
        $this->assertSame('One', SavedReport::query()->find($one->id)->name);
        $this->assertNotNull($tokenId);
    }

    public function testATokenIsLimitedToResourcesWhenItIsCreated(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->rowsReport($admin);

        $created = $this->actingAs($admin, 'api')->postJson('/api/auth/tokens', ['name' => 'Power BI', 'scopes' => ['reports:read'], 'kind' => 'integration', 'resource_ids' => [$report->id]])->assertCreated();
        $created->assertJsonPath('data.resource_ids', [$report->id]);
        $this->actingAs($admin, 'api')->getJson('/api/auth/tokens')->assertJsonPath('data.0.resource_ids', [$report->id]);
        $this->actingAs($admin, 'api')->postJson('/api/auth/tokens', ['name' => 'plain', 'scopes' => ['reports:read']])->assertCreated()->assertJsonPath('data.resource_ids', null);
        foreach ([[], ['short'], [str_repeat('!', 26)], [123], array_fill(0, 21, $report->id)] as $bad) {
            $this->actingAs($admin, 'api')->postJson('/api/auth/tokens', ['name' => 'bad', 'scopes' => ['reports:read'], 'resource_ids' => $bad])->assertStatus(422);
        }
    }

    public function testALimitedTokenOnAnythingElseOfThePlatformIsClosed(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->rowsReport($admin);
        [$limited] = $this->token($admin, ['*'], [$report->id]);

        $this->feed('/api/admin/reporting/datasets', $this->bearer($limited))->assertForbidden()->assertJsonPath('error', 'resource_limited');
        $this->feed($this->entity($report), $this->bearer($limited))->assertOk();
    }

    // ---- limits, logging, isolation ----

    public function testAFeedAnswers429AfterSixtyRequestsAMinute(): void
    {
        $admin = $this->makeAdmin();
        [$secret] = $this->token($admin);

        for ($i = 0; $i < 60; $i++) {
            $this->feed(self::ROOT, $this->bearer($secret))->assertOk();
        }
        $this->feed(self::ROOT, $this->bearer($secret))->assertStatus(429);
        [$other] = $this->token($admin);
        $this->feed(self::ROOT, $this->bearer($other))->assertOk();
    }

    public function testEveryEntitySetRequestIsRecordedAsAFeedExport(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->rowsReport($admin);
        $this->calls('a', [1, 2, 3]);
        [$secret] = $this->token($admin);

        $this->feed($this->entity($report), $this->bearer($secret))->assertOk();
        $this->feed($this->entity($report) . '?$top=2', $this->bearer($secret))->assertOk();
        $this->feed(self::ROOT, $this->bearer($secret))->assertOk();

        $log = DB::table('compliance_exports')->where('channel', 'feed')->orderBy('id')->get();
        $this->assertCount(2, $log, 'the service document and the metadata are not exports');
        $this->assertSame([3, 2], $log->pluck('row_count')->map(fn ($n) => (int) $n)->all());
        $this->assertSame(['odata', $report->id, $admin->getKey(), 'learner_calls'], [$log[0]->format, $log[0]->saved_report_id, (int) $log[0]->user_id, $log[0]->dataset]);
        $this->assertNotNull(SavedReport::query()->find($report->id)->last_run_at);
    }

    public function testReadsOfTheFeedAreInTheTokenAuditLog(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->rowsReport($admin);
        [$secret, $tokenId] = $this->token($admin);

        $this->feed($this->entity($report), $this->bearer($secret))->assertOk();
        $this->feed($this->entity($report), $this->basic($secret))->assertOk();

        $rows = DB::table('agent_audit_log')->where('token_id', $tokenId)->get();
        $this->assertCount(2, $rows);
        $this->assertSame('/api/reporting/odata/Report_' . $report->id, $rows[0]->path);
        $this->assertSame('GET', $rows[0]->method);
    }

    public function testAFeedIsEnabledOnlyByWhoManagesReports(): void
    {
        $owner = $this->makeInstructor();
        $owner->givePermissionTo(['reporting_create', 'reporting_ai_usage']);
        $body = ['name' => 'Calls', 'definition' => ['dataset' => 'learner_calls', 'columns' => ['task']]];

        $this->actingAs($owner, 'api')->postJson('/api/admin/reporting/saved', $body + ['feed_enabled' => true])->assertForbidden();
        $id = $this->actingAs($owner, 'api')->postJson('/api/admin/reporting/saved', $body)->assertCreated()->assertJsonPath('data.feed_enabled', false)->assertJsonPath('data.feed', null)->json('data.id');
        $this->actingAs($owner, 'api')->putJson("/api/admin/reporting/saved/{$id}", ['feed_enabled' => true])->assertForbidden();
        $this->actingAs($owner, 'api')->putJson("/api/admin/reporting/saved/{$id}", ['name' => 'Renamed'])->assertOk();

        $manager = $this->makeAdmin();
        $data = $this->actingAs($manager, 'api')->putJson("/api/admin/reporting/saved/{$id}", ['feed_enabled' => true])->assertOk()->json('data');
        $this->assertSame([true, 'Report_' . $id, url('/api/reporting/odata') . '/'], [$data['feed_enabled'], $data['feed']['entity_set'], $data['feed']['url']]);
        $this->actingAs($manager, 'api')->putJson("/api/admin/reporting/saved/{$id}", ['feed_enabled' => false])->assertOk()->assertJsonPath('data.feed', null);
    }

    public function testATokenOfAnotherTenantIsRejectedOnEveryFeedRoute(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->rowsReport($admin);
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/reporting/odata'))
            ->flatMap(fn ($r) => collect($r->methods())->reject(fn ($m) => in_array($m, ['HEAD', 'OPTIONS']))->map(fn ($m) => "{$m} {$r->uri()}"))->sort()->values()->all();
        $this->assertSame(['GET api/reporting/odata', 'GET api/reporting/odata/$metadata', 'GET api/reporting/odata/{entity}'], $routes, 'a new feed route needs its isolation check here');

        [$secret] = $this->token($admin);
        $urls = [self::ROOT, self::ROOT . '/$metadata', $this->entity($report)];
        foreach ($urls as $url) {
            $this->feed($url, $this->bearer($secret))->assertOk();
        }
        $this->switchToTenantWithOwnKeys();
        foreach ($urls as $url) {
            $this->sendAs($secret, 'GET', $url)->assertUnauthorized();
            $this->feed($url, $this->basic($secret))->assertUnauthorized();
        }
    }
}
