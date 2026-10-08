<?php

namespace Ulams\Cmi5\Tests\Api;

use Ulams\Cmi5\Database\Seeders\Cmi5PermissionSeeder;
use Ulams\Cmi5\Tests\TestCase;
use Ulams\Cmi5\Tests\Traits\Cmi5Testing;
use Ulams\Core\Tests\CreatesUsers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

class Cmi5ApiUploadTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, Cmi5Testing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Cmi5PermissionSeeder::class);
    }

    public static function cmiFileProvider(): array
    {
        return [
            [
                'fileName' => 'cmi5.zip',
                'auAmount' => 1
            ],
            [
                'fileName' => 'cmi5_multi_au_framed.zip',
                'auAmount' => 8
            ],
        ];
    }

    #[DataProvider('cmiFileProvider')]
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
