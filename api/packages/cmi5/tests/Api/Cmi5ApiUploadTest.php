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

    public static function hostileEntries(): array
    {
        return [
            'zip-slip' => ['../../../public/evil.php', false, 'leaves its folder'],
            'absolute path' => ['/var/www/html/public/evil.php', false, 'absolute path'],
            'symlink' => ['leak.txt', true, 'symbolic link'],
        ];
    }

    #[DataProvider('hostileEntries')]
    public function testHostilePackageIsRejectedAndNothingIsStored(string $name, bool $symlink, string $message): void
    {
        Storage::fake(config('ulams_cmi5.disk'));
        $path = $this->copyMockWith($name, $symlink);

        $response = $this->actingAs($this->makeAdmin(), 'api')->json('POST', '/api/admin/cmi5', [
            'file' => new UploadedFile($path, 'cmi5.zip', null, null, true),
        ]);
        @unlink($path);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['file' => $message]);
        $this->assertSame([], Storage::disk(config('ulams_cmi5.disk'))->allFiles());
        $this->assertDatabaseCount('cmi5s', 0);
    }

    public function testServiceRejectsZipSlipWithoutRequestValidation(): void
    {
        Storage::fake(config('ulams_cmi5.disk'));
        $path = $this->copyMockWith('../escape.html', false);

        try {
            app(\Ulams\Cmi5\Services\Contracts\Cmi5UploadServiceContract::class)
                ->upload(new UploadedFile($path, 'cmi5.zip', null, null, true));
            $this->fail('Expected the package to be rejected.');
        } catch (\Ulams\Uploads\Exceptions\UploadRejected $e) {
            $this->assertSame('zip_slip', $e->reason);
        } finally {
            @unlink($path);
        }
        $this->assertDatabaseCount('cmi5s', 0);
        $this->assertSame([], Storage::disk(config('ulams_cmi5.disk'))->allFiles());
    }

    private function copyMockWith(string $name, bool $symlink): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ulams-cmi5-');
        copy(__DIR__ . '/../mocks/cmi5.zip', $path);
        $zip = new \ZipArchive();
        $zip->open($path);
        $zip->addFromString($name, $symlink ? '/var/www/html/.env' : '<?php echo 1;');
        if ($symlink) {
            $zip->setExternalAttributesName($name, \ZipArchive::OPSYS_UNIX, 0120777 << 16);
        }
        $zip->close();

        return $path;
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
