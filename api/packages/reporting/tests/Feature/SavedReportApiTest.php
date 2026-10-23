<?php

namespace Ulams\Reporting\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Reporting\Models\SavedReport;
use Ulams\Reporting\Tests\TestCase;

/** Saved reports: who may save, see, change and run them, sharing by role, the viewer's own scope, exports. */
class SavedReportApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerLearnerDatasets();
    }

    private function definition(array $extra = []): array
    {
        return $extra + ['dataset' => 'learner_calls', 'columns' => ['task', 'user_id'], 'sort' => [['column' => 'user_id']]];
    }

    private function save(object $as, array $extra = []): string
    {
        return $this->actingAs($as, 'api')->postJson('/api/admin/reporting/saved', $extra + ['name' => 'Calls', 'definition' => $this->definition()])->assertCreated()->json('data.id');
    }

    /** A user of a role the tenant added, with the permission to read reports. */
    private function partner(string $role = 'partner', array $permissions = ['reporting_read', 'reporting_ai_usage'])
    {
        Role::findOrCreate($role, 'api');
        foreach ($permissions as $permission) {
            Role::findOrCreate($role, 'api')->givePermissionTo(Permission::findOrCreate($permission, 'api'));
        }
        $user = config('auth.providers.users.model')::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** A tutor who builds reports on the test dataset but does not manage other people's (direct permissions, not a role). */
    private function builder()
    {
        $user = $this->makeInstructor();
        $user->givePermissionTo(['reporting_create', 'reporting_ai_usage']);

        return $user;
    }

    public function testOnlyAdministratorsWhoBuildReportsCanSaveOne(): void
    {
        $body = ['name' => 'Calls', 'definition' => $this->definition()];

        $this->postJson('/api/admin/reporting/saved', $body)->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')->postJson('/api/admin/reporting/saved', $body)->assertForbidden();
        $this->actingAs($this->makeInstructor(), 'api')->postJson('/api/admin/reporting/saved', $body)->assertForbidden();

        $admin = $this->makeAdmin();
        $data = $this->actingAs($admin, 'api')->postJson('/api/admin/reporting/saved', $body + ['description' => 'Who called what'])->assertCreated()->json('data');
        $this->assertSame(26, strlen($data['id']));
        $this->assertSame(['Calls', 'Who called what', 'private', [], 'learner_calls', 1, $admin->getKey(), true, true, false, []],
            [$data['name'], $data['description'], $data['visibility'], $data['shared_roles'], $data['dataset'], $data['definition_version'], $data['owner_id'], $data['is_owner'], $data['can_edit'], $data['needs_repair'], $data['problems']]);
        $this->assertSame($this->definition(), $data['definition']);
    }

    public function testASavedDefinitionIsValidatedLikeAQueryAndOnADatasetTheSaverMayRun(): void
    {
        $admin = $this->makeAdmin();
        $post = fn (array $body) => $this->actingAs($admin, 'api')->postJson('/api/admin/reporting/saved', $body);

        $post(['name' => 'x', 'definition' => ['dataset' => 'learner_calls', 'columns' => ['task', 'nope']]])->assertStatus(422)->assertJsonValidationErrors(['definition.columns.1']);
        $post(['name' => 'x', 'definition' => ['dataset' => 'nope', 'columns' => ['task']]])->assertStatus(422)->assertJsonValidationErrors(['definition.dataset']);
        $post(['definition' => $this->definition()])->assertStatus(422)->assertJsonValidationErrors(['name']);
        $post(['name' => 'x'])->assertStatus(422)->assertJsonValidationErrors(['definition']);

        $builder = $this->makeInstructor();
        $builder->givePermissionTo('reporting_create');
        $this->actingAs($builder, 'api')->postJson('/api/admin/reporting/saved', ['name' => 'x', 'definition' => $this->definition()])->assertStatus(422)->assertJsonValidationErrors(['definition.dataset']);
        $this->assertSame(0, SavedReport::query()->count());
    }

    public function testAPrivateReportIsSeenByItsOwnerAndByWhoManagesReports(): void
    {
        $owner = $this->makeAdmin();
        $id = $this->save($owner);
        $otherAdmin = $this->builder();

        $this->actingAs($owner, 'api')->getJson("/api/admin/reporting/saved/{$id}")->assertOk();
        $this->actingAs($this->makeAdmin(), 'api')->getJson("/api/admin/reporting/saved/{$id}")->assertOk();
        $this->actingAs($otherAdmin, 'api')->getJson("/api/admin/reporting/saved/{$id}")->assertNotFound();
        $this->actingAs($otherAdmin, 'api')->getJson('/api/admin/reporting/saved')->assertOk()->assertJsonPath('data', []);
        $this->actingAs($this->makeInstructor(), 'api')->getJson("/api/admin/reporting/saved/{$id}")->assertNotFound();
        $this->actingAs($this->makeStudent(), 'api')->getJson('/api/admin/reporting/saved')->assertForbidden();
    }

    public function testSharingWithARoleShowsTheReportToThatRoleOnly(): void
    {
        $owner = $this->makeAdmin();
        $partner = $this->partner();
        $outsider = $this->partner('auditor');
        $id = $this->save($owner, ['visibility' => 'roles', 'shared_roles' => ['partner']]);

        $list = $this->actingAs($partner, 'api')->getJson('/api/admin/reporting/saved')->assertOk()->json('data');
        $this->assertSame([$id], array_column($list, 'id'));
        $this->assertFalse($list[0]['is_owner']);
        $this->assertFalse($list[0]['can_edit']);
        $this->assertTrue($list[0]['runnable']);
        $this->actingAs($partner, 'api')->getJson("/api/admin/reporting/saved/{$id}")->assertOk()->assertJsonPath('data.shared_roles', ['partner']);
        $this->actingAs($outsider, 'api')->getJson("/api/admin/reporting/saved/{$id}")->assertNotFound();
        $this->actingAs($outsider, 'api')->getJson('/api/admin/reporting/saved')->assertJsonPath('data', []);

        $this->actingAs($owner, 'api')->putJson("/api/admin/reporting/saved/{$id}", ['visibility' => 'private'])->assertOk()->assertJsonPath('data.shared_roles', []);
        $this->actingAs($partner, 'api')->getJson("/api/admin/reporting/saved/{$id}")->assertNotFound();
    }

    public function testSharingNeedsRolesThatExist(): void
    {
        $admin = $this->makeAdmin();
        $body = ['name' => 'x', 'definition' => $this->definition()];

        $this->actingAs($admin, 'api')->postJson('/api/admin/reporting/saved', $body + ['visibility' => 'roles'])->assertStatus(422)->assertJsonValidationErrors(['shared_roles']);
        $this->actingAs($admin, 'api')->postJson('/api/admin/reporting/saved', $body + ['visibility' => 'roles', 'shared_roles' => ['no-such-role']])->assertStatus(422)->assertJsonValidationErrors(['shared_roles']);
        $this->actingAs($admin, 'api')->postJson('/api/admin/reporting/saved', $body + ['visibility' => 'roles', 'shared_roles' => ['tutor', 'tutor']])->assertCreated()->assertJsonPath('data.shared_roles', ['tutor']);
        $this->actingAs($admin, 'api')->postJson('/api/admin/reporting/saved', $body + ['visibility' => 'private', 'shared_roles' => ['nope']])->assertCreated()->assertJsonPath('data.shared_roles', []);
    }

    public function testOnlyTheOwnerOrAManagerChangesOrDeletes(): void
    {
        $owner = $this->makeAdmin();
        $partner = $this->partner();
        $peer = $this->builder();
        $id = $this->save($owner, ['visibility' => 'roles', 'shared_roles' => ['partner', 'tutor']]);

        $this->actingAs($partner, 'api')->putJson("/api/admin/reporting/saved/{$id}", ['name' => 'Mine now'])->assertForbidden();
        $this->actingAs($partner, 'api')->deleteJson("/api/admin/reporting/saved/{$id}")->assertForbidden();
        $this->actingAs($peer, 'api')->putJson("/api/admin/reporting/saved/{$id}", ['name' => 'Mine now'])->assertForbidden();
        $this->assertSame('Calls', SavedReport::query()->find($id)->name);

        $this->actingAs($owner, 'api')->putJson("/api/admin/reporting/saved/{$id}", ['name' => 'Renamed', 'description' => 'd', 'definition' => $this->definition(['limit' => 5])])->assertOk()->assertJsonPath('data.name', 'Renamed');
        $this->assertSame(5, SavedReport::query()->find($id)->definition['limit']);
        $this->actingAs($this->makeAdmin(), 'api')->putJson("/api/admin/reporting/saved/{$id}", ['name' => 'By a manager'])->assertOk();
        $this->actingAs($owner, 'api')->putJson("/api/admin/reporting/saved/{$id}", ['definition' => ['dataset' => 'learner_calls', 'columns' => ['nope']]])->assertStatus(422)->assertJsonValidationErrors(['definition.columns.0']);

        $this->actingAs($owner, 'api')->deleteJson("/api/admin/reporting/saved/{$id}")->assertOk();
        $this->actingAs($owner, 'api')->getJson("/api/admin/reporting/saved/{$id}")->assertNotFound();
        $this->actingAs($owner, 'api')->getJson('/api/admin/reporting/saved')->assertJsonPath('data', []);
        $this->assertSoftDeleted('saved_reports', ['id' => $id]);
    }

    public function testTheOwnerNeedsToStillBuildReportsToChangeOneAndAMissingIdIs404(): void
    {
        $owner = $this->builder();
        $id = $this->save($owner);
        $owner->revokePermissionTo('reporting_create');

        $this->actingAs($owner, 'api')->putJson("/api/admin/reporting/saved/{$id}", ['name' => 'x'])->assertForbidden();
        $this->actingAs($this->makeAdmin(), 'api')->getJson('/api/admin/reporting/saved/' . str_repeat('0', 26))->assertNotFound();
        $this->actingAs($this->makeAdmin(), 'api')->getJson('/api/admin/reporting/saved/not-an-id')->assertNotFound();
    }

    public function testASharedReportRunsWithTheViewersOwnScope(): void
    {
        $owner = $this->makeAdmin();
        $partner = $this->partner('partner', ['reporting_read']);
        $other = $this->partner('partner');
        $this->calls('mine', [$partner->getKey()]);
        $this->calls('theirs', [$other->getKey(), $other->getKey()]);
        $this->calls('owners', [$owner->getKey()]);
        $id = $this->save($owner, ['visibility' => 'roles', 'shared_roles' => ['partner'], 'definition' => ['dataset' => 'my_calls', 'columns' => ['task', 'user_id'], 'sort' => [['column' => 'task']]]]);

        $asOwner = $this->actingAs($owner, 'api')->getJson("/api/admin/reporting/saved/{$id}/run")->assertOk();
        $this->assertSame(['mine', 'owners', 'theirs', 'theirs'], array_column($asOwner->json('data.rows'), 'task'), 'a manager sees every row');

        $asPartner = $this->actingAs($partner, 'api')->getJson("/api/admin/reporting/saved/{$id}/run")->assertOk();
        $this->assertSame(['mine'], array_column($asPartner->json('data.rows'), 'task'), 'the same report, the partner sees only their own rows');
        $this->assertSame(1, $asPartner->json('meta.total_rows'));

        $asOther = $this->actingAs($other, 'api')->getJson("/api/admin/reporting/saved/{$id}/run")->assertOk();
        $this->assertSame(['theirs', 'theirs'], array_column($asOther->json('data.rows'), 'task'));
    }

    public function testAViewerWithoutThePermissionOfTheDatasetCannotRunIt(): void
    {
        $noDataset = $this->partner('partner', ['reporting_read']);
        $id = $this->save($this->makeAdmin(), ['visibility' => 'roles', 'shared_roles' => ['partner']]);

        $list = $this->actingAs($noDataset, 'api')->getJson('/api/admin/reporting/saved')->assertOk()->json('data');
        $this->assertFalse($list[0]['runnable']);
        $this->actingAs($noDataset, 'api')->getJson("/api/admin/reporting/saved/{$id}/run")->assertForbidden();
        $this->actingAs($noDataset, 'api')->getJson("/api/admin/reporting/saved/{$id}/run?format=csv")->assertForbidden();
        $this->assertSame(0, DB::table('compliance_exports')->count());
    }

    public function testARunRecordsWhenItHappenedAndFormatsAreChecked(): void
    {
        $admin = $this->makeAdmin();
        $id = $this->save($admin);
        $this->assertNull(SavedReport::query()->find($id)->last_run_at);

        $this->actingAs($admin, 'api')->getJson("/api/admin/reporting/saved/{$id}/run?format=json")->assertOk();
        $this->assertNotNull(SavedReport::query()->find($id)->last_run_at);
        $this->actingAs($admin, 'api')->getJson("/api/admin/reporting/saved/{$id}/run?format=pdf")->assertStatus(422);
    }

    public function testACsvExportIsStreamedNeutralisedAndRecorded(): void
    {
        $this->calls('=SUM(A1)', [1, 2]);
        $this->calls('-1+1', [3]);
        $admin = $this->makeAdmin();
        $id = $this->save($admin, ['definition' => $this->definition(['columns' => ['task', 'user_id', 'cost_usd', 'cached']])]);

        $response = $this->actingAs($admin, 'api')->get("/api/admin/reporting/saved/{$id}/run?format=csv")->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('calls-', $response->headers->get('Content-Disposition'));
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBFtask,user_id,cost_usd,cached\n", $csv);
        $lines = array_values(array_filter(explode("\n", $csv)));
        $this->assertCount(4, $lines);
        $this->assertStringStartsWith("'=SUM(A1),1,1,false", $lines[1]);
        $this->assertStringStartsWith("'-1+1,3,1,false", $lines[3]);

        $log = DB::table('compliance_exports')->first();
        $this->assertSame([$admin->getKey(), 'learner_calls', 'csv', 3, $id, 'download'], [$log->user_id, $log->dataset, $log->format, $log->row_count, $log->saved_report_id, $log->channel]);
        $this->assertSame('learner_calls', json_decode($log->filters, true)['report']['dataset']);
    }

    public function testAnXlsxExportIsAValidWorkbookWithTheLabelsAsHeaders(): void
    {
        $this->calls('=SUM(A1)', [1, 2], ['input_tokens' => 40]);
        $admin = $this->makeAdmin();
        $id = $this->save($admin, ['definition' => $this->definition(['columns' => ['task', 'user_id', 'cost_usd', 'cached', 'input_tokens']])]);

        $response = $this->actingAs($admin, 'api')->get("/api/admin/reporting/saved/{$id}/run?format=xlsx")->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));
        $path = tempnam(sys_get_temp_dir(), 'xlsx-api-');
        file_put_contents($path, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        $this->assertSame(['Task', 'Learner', 'Cost', 'Cached', 'Input tokens'], $sheet->rangeToArray('A1:E1')[0]);
        $this->assertSame('=SUM(A1)', $sheet->getCell('A2')->getFormattedValue());
        $this->assertSame([1, 2], [$sheet->getCell('B2')->getValue(), $sheet->getCell('B3')->getValue()]);
        $this->assertSame(40, $sheet->getCell('E2')->getValue());
        $this->assertFalse($sheet->getCell('D2')->getValue());
        $log = DB::table('compliance_exports')->first();
        $this->assertSame(['xlsx', 2, 'download'], [$log->format, $log->row_count, $log->channel]);
    }

    public function testAJsonLinesExportWritesOneObjectPerRowAndHonoursTheCaps(): void
    {
        $this->calls('a', range(1, 5));
        $admin = $this->makeAdmin();
        $id = $this->save($admin);

        $lines = array_values(array_filter(explode("\n", $this->actingAs($admin, 'api')->get("/api/admin/reporting/saved/{$id}/run?format=jsonl")->assertOk()->streamedContent())));
        $this->assertCount(5, $lines);
        $this->assertSame(['task' => 'a', 'user_id' => 1], json_decode($lines[0], true));

        config(['reporting.export_rows' => 3]);
        $this->assertCount(3, array_filter(explode("\n", $this->actingAs($admin, 'api')->get("/api/admin/reporting/saved/{$id}/run?format=jsonl")->streamedContent())));
        $this->assertSame([5, 3], DB::table('compliance_exports')->orderBy('id')->pluck('row_count')->map(fn ($n) => (int) $n)->all());
    }

    public function testWithheldGroupsStayWithheldInEveryExportFormat(): void
    {
        $this->calls('big', range(1, 12));
        $this->calls('small', range(20, 23));
        $this->calls('bigger', range(30, 50));
        $admin = $this->makeAdmin();
        $id = $this->save($admin, ['definition' => ['dataset' => 'learner_calls', 'groupBy' => ['task'], 'aggregates' => [['fn' => 'count_distinct', 'column' => 'user_id', 'as' => 'learners']], 'sort' => [['column' => 'task']]]]);

        $csv = $this->actingAs($admin, 'api')->get("/api/admin/reporting/saved/{$id}/run?format=csv")->streamedContent();
        $jsonl = $this->actingAs($admin, 'api')->get("/api/admin/reporting/saved/{$id}/run?format=jsonl")->streamedContent();
        $json = $this->actingAs($admin, 'api')->getJson("/api/admin/reporting/saved/{$id}/run")->json('data.rows');

        $this->assertStringContainsString('bigger,21', $csv);
        $this->assertStringContainsString('"withheld (fewer than 10 learners)",', $csv);
        $this->assertStringNotContainsString('small', $csv . $jsonl . json_encode($json));
        $this->assertStringNotContainsString("big,", $csv, 'the smallest visible group goes with the small one');
        $rows = array_map(fn ($l) => json_decode($l, true), array_values(array_filter(explode("\n", $jsonl))));
        $this->assertTrue($rows[1]['_withheld']);
        $this->assertNull($rows[1]['learners']);
        $this->assertSame(2, count($rows));
        $this->assertSame(2, count($json));
    }

    public function testAReportWhoseColumnWasDroppedNeedsRepairInsteadOfFailingSilently(): void
    {
        $admin = $this->makeAdmin();
        $id = $this->save($admin);
        DB::table('saved_reports')->where('id', $id)->update(['definition' => json_encode(['dataset' => 'learner_calls', 'columns' => ['task', 'renamed_column']])]);

        $item = $this->actingAs($admin, 'api')->getJson("/api/admin/reporting/saved/{$id}")->assertOk()->json('data');
        $this->assertTrue($item['needs_repair']);
        $this->assertFalse($item['runnable']);
        $this->assertSame('/columns/1', $item['problems'][0]['path']);

        $this->actingAs($admin, 'api')->getJson("/api/admin/reporting/saved/{$id}/run")->assertStatus(409)->assertJsonPath('errors.0.path', '/columns/1');
        $this->actingAs($admin, 'api')->get("/api/admin/reporting/saved/{$id}/run?format=csv")->assertStatus(409);

        $this->actingAs($admin, 'api')->putJson("/api/admin/reporting/saved/{$id}", ['definition' => $this->definition()])->assertOk()->assertJsonPath('data.needs_repair', false);
        $this->actingAs($admin, 'api')->getJson("/api/admin/reporting/saved/{$id}/run")->assertOk();
    }

    public function testAReportOnAMissingDatasetNeedsRepairToo(): void
    {
        $admin = $this->makeAdmin();
        $id = $this->save($admin);
        DB::table('saved_reports')->where('id', $id)->update(['definition' => json_encode(['dataset' => 'removed_dataset', 'columns' => ['x']])]);

        $item = $this->actingAs($admin, 'api')->getJson('/api/admin/reporting/saved')->assertOk()->json('data.0');

        $this->assertTrue($item['needs_repair']);
        $this->assertFalse($item['runnable']);
        $this->actingAs($admin, 'api')->getJson("/api/admin/reporting/saved/{$id}/run")->assertForbidden();
    }
}
