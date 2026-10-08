<?php

namespace EscolaLms\BulkNotifications\Tests\Api;

use EscolaLms\BulkNotifications\Database\Seeders\BulkNotificationPermissionSeeder;
use EscolaLms\BulkNotifications\Models\BulkNotification;
use EscolaLms\BulkNotifications\Models\BulkNotificationSection;
use EscolaLms\BulkNotifications\Tests\BulkNotificationTesting;
use EscolaLms\BulkNotifications\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;

class ListBulkNotificationApiTest extends TestCase
{
    use CreatesUsers, BulkNotificationTesting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BulkNotificationPermissionSeeder::class);
    }

    public function testListBulkNotification(): void
    {
        BulkNotification::factory()
            ->count(5)
            ->has(BulkNotificationSection::factory()->count(5), 'sections')
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/bulk-notifications')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'channel',
                    'sections' => [[
                        'id',
                        'key',
                        'value',
                    ]],
                    'users',
                ]]
            ]);
    }

    public function testListBulkNotificationPagination(): void
    {
        BulkNotification::factory()
            ->count(25)
            ->has(BulkNotificationSection::factory()->count(5), 'sections')
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/bulk-notifications?per_page=15&page=1')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'channel',
                    'sections' => [[
                        'id',
                        'key',
                        'value',
                    ]],
                    'users',
                ]]
            ]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/bulk-notifications?per_page=15&page=2')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'channel',
                    'sections' => [[
                        'id',
                        'key',
                        'value',
                    ]],
                    'users',
                ]]
            ]);
    }

    public function testListBulkNotificationFiltering(): void
    {
        $channel = 'EscolaLms\BulkNotifications\Channels\TestingChannel';

        BulkNotification::factory()
            ->count(10)
            ->has(BulkNotificationSection::factory()->count(5), 'sections')
            ->create();

        BulkNotification::factory()
            ->count(5)
            ->state(['channel' => $channel])
            ->has(BulkNotificationSection::factory()->count(5), 'sections')
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/bulk-notifications?channel=' . $channel)
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'channel',
                    'sections' => [[
                        'id',
                        'key',
                        'value',
                    ]],
                    'users',
                ]]
            ]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/bulk-notifications')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'channel',
                    'sections' => [[
                        'id',
                        'key',
                        'value',
                    ]],
                    'users',
                ]]
            ]);
    }

    public function testListBulkNotificationForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->getJson('api/admin/bulk-notifications')
            ->assertForbidden();
    }

    public function testListBulkNotificationUnauthorized(): void
    {
        $this->getJson('api/admin/bulk-notifications')
            ->assertUnauthorized();
    }
}
