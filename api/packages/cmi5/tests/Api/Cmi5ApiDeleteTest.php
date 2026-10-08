<?php

namespace EscolaLms\Cmi5\Tests\Api;

use EscolaLms\Cmi5\Database\Seeders\Cmi5PermissionSeeder;
use EscolaLms\Cmi5\Tests\TestCase;
use EscolaLms\Cmi5\Tests\Traits\Cmi5Testing;
use EscolaLms\Core\Tests\CreatesUsers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;

class Cmi5ApiDeleteTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, Cmi5Testing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Cmi5PermissionSeeder::class);
    }

    public function testDeleteCmi5(): void
    {
        Storage::fake();
        $admin = $this->makeAdmin();
        $data = $this->uploadCmi5('cmi5.zip');
        $cmi5Id = $data->id;

        Storage::exists('cmi5/' . $cmi5Id);
        $this->assertDatabaseHas('cmi5s', ['id' => $cmi5Id]);
        $this->assertDatabaseHas('cmi5_aus', ['id' => $data->au[0]->id]);

        $this->actingAs($admin, 'api')
            ->json('DELETE', '/api/admin/cmi5/' . $cmi5Id)
            ->assertOk();

        Storage::missing('cmi5/' . $cmi5Id);
        $this->assertDatabaseMissing('cmi5s', ['id' => $cmi5Id]);
        $this->assertDatabaseMissing('cmi5_aus', ['id' => $data->au[0]->id]);
    }

    public function testDeleteNotExistingCmi5(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'api')
            ->json('DELETE', '/api/admin/cmi5/' . 123)
            ->assertNotFound();
    }

    public function testDeleteForbidden(): void
    {
        Storage::fake();
        $data = $this->uploadCmi5('cmi5.zip');
        $cmi5Id = $data->id;

        $student = $this->makeStudent();

        $this->actingAs($student, 'api')
            ->json('DELETE', '/api/admin/cmi5/' . $cmi5Id)
            ->assertForbidden();
    }

    public function testDeleteUnauthorized(): void
    {
        Storage::fake();
        $data = $this->uploadCmi5('cmi5.zip');
        $cmi5Id = $data->id;

        $this->refreshApplication();

        $this->json('DELETE', '/api/admin/cmi5/' . $cmi5Id)
            ->assertUnauthorized();
    }
}
