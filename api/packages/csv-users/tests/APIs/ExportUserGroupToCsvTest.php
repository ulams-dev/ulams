<?php

namespace EscolaLms\CsvUsers\Tests\APIs;

use EscolaLms\Auth\Models\User;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\CsvUsers\Export\UserGroupExport;
use EscolaLms\CsvUsers\Models\Group;
use EscolaLms\CsvUsers\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Maatwebsite\Excel\Facades\Excel;

class ExportUserGroupToCsvTest extends TestCase
{
    use CreatesUsers, WithFaker, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Excel::fake();
    }

    public function testUnauthorizedAccessToExportGroupToCsv(): void
    {
        $response = $this->getJson('/api/admin/csv/users');
        $response->assertUnauthorized();
    }

    public function testAccessToExportGroupToCSV(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'api')->getJson('/api/admin/csv/users');
        $response->assertOk();

        Excel::assertDownloaded('users.csv');
    }

    public function testExportUserGroupToCsv(): void
    {
        $admin = $this->makeAdmin();

        $group = Group::factory()->create([
            'name' => 'Test Group',
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $group->users()->saveMany([$user1, $user2]);

        $response = $this->actingAs($admin, 'api')->json('get', '/api/admin/csv/groups/' . $group->getKey());
        $response->assertOk();

        Excel::assertDownloaded('group.csv', function (UserGroupExport $export) use ($group, $user1, $user2) {
            return $export->collection()->contains('email', $user1->email)
                && $export->collection()->contains('email', $user2->email)
                && $export->collection()->contains('group_name', $group->name);
        });
    }
}
