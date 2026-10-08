<?php

namespace EscolaLms\Cmi5\Tests\Api;

use EscolaLms\Cmi5\Database\Seeders\Cmi5PermissionSeeder;
use EscolaLms\Cmi5\Tests\TestCase;
use EscolaLms\Cmi5\Tests\Traits\Cmi5Testing;
use EscolaLms\Core\Tests\CreatesUsers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Cmi5ApiUploadTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, Cmi5Testing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Cmi5PermissionSeeder::class);
    }

    public function cmiFileProvider(): array
    {
        return [
            [
                'file' => 'cmi5.zip',
                'au_amount' => 1
            ],
            [
                'file' => 'cmi5_multi_au_framed.zip',
                'au_amount' => 8
            ],
        ];
    }

    /**
     * @dataProvider cmiFileProvider
     */
    public function testUploadCmi5(string $fileName, int $auAmount): void
    {
        Storage::fake();
        $file = $this->getCmi5UploadedFile($fileName);
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'api')
            ->json('POST', '/api/admin/cmi5', ['file' => $file]);

        $response->assertCreated();
        $response->assertJsonCount($auAmount, 'data.au');
        $response->assertJsonStructure([
            'data' => [
                'id',
                'iri',
                'title',
                'au' => [[
                    'id',
                    'title',
                    'url'
                ]]
            ]
        ]);

        Storage::exists('cmi5/' . $response->getData()->data->id);
    }

    public function testUploadInvalidCmi5(): void
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin, 'api')
            ->json(
                'POST',
                '/api/admin/cmi5',
                ['file' => UploadedFile::fake()->create('cmi5.zip', 100, 'application/zip')]
            );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['file' => 'Invalid cmi5 file.']);
    }

    public function testUploadCmi5Unauthorized(): void
    {
        $response = $this->json('POST','/api/admin/cmi5');
        $response->assertUnauthorized();
    }

    public function testUploadCmi5Forbidden(): void
    {
        $student = $this->makeStudent();
        $response = $this->actingAs($student, 'api')->json('POST','/api/admin/cmi5');
        $response->assertForbidden();
    }
}
