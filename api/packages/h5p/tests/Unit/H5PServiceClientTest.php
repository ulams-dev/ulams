<?php

namespace Ulams\H5P\Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Ulams\H5P\Database\Factories\H5PContentFactory;
use Ulams\H5P\Exceptions\H5PContentReadOnlyException;
use Ulams\H5P\Exceptions\H5PServiceException;
use Ulams\H5P\Models\H5PContent;
use Ulams\H5P\Services\Contracts\H5PServiceClientContract;
use Ulams\H5P\Testing\H5PServiceFake;
use Ulams\H5P\Tests\TestCase;
use ZipArchive;

class H5PServiceClientTest extends TestCase
{
    private function client(): H5PServiceClientContract
    {
        return app(H5PServiceClientContract::class);
    }

    private function mockPackage(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'h5p-test-') . '.h5p';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('h5p.json', json_encode([
            'title' => 'Mock quiz',
            'mainLibrary' => 'H5P.MultiChoice',
            'preloadedDependencies' => [['machineName' => 'H5P.MultiChoice', 'majorVersion' => 1, 'minorVersion' => 16]],
        ]));
        $zip->addFromString('content/content.json', json_encode(['answers' => [['text' => 'a', 'correct' => true]]]));
        $zip->close();

        return $path;
    }

    public function testUploadSendsMultipartWithInternalToken(): void
    {
        Http::fake(['http://h5p.test:8080/h5p/contents/upload' => Http::response([
            'success' => true,
            'data' => ['contentId' => '42', 'metadata' => [], 'installedLibraries' => []],
            'message' => 'Content uploaded',
        ], 201)]);
        $path = $this->mockPackage();

        $this->assertSame(42, $this->client()->upload($path));

        Http::assertSent(function (Request $request) use ($path) {
            $part = collect($request->data())->firstWhere('name', 'h5p_file');

            return $request->method() === 'POST'
                && $request->hasHeader('X-Internal-Token', 'test-internal-token')
                && $request->hasHeader('X-Forwarded-Host', parse_url((string) config('app.url'), PHP_URL_HOST))
                && $request->isMultipart()
                && $part['filename'] === basename($path);
        });
        @unlink($path);
    }

    public function testUploadOfUploadedFileCreatesContent(): void
    {
        H5PServiceFake::fake();
        $path = $this->mockPackage();
        $file = new UploadedFile($path, 'export', 'application/zip', null, true);

        $id = $this->client()->upload($file);

        $content = H5PContent::query()->findOrFail($id);
        $this->assertSame('Mock quiz', $content->title);
        $this->assertSame('H5P.MultiChoice 1.16', $content->library);
        // the service rejects names without the .h5p extension
        Http::assertSent(fn (Request $request) => collect($request->data())->firstWhere('name', 'h5p_file')['filename'] === 'package.h5p');
        @unlink($path);
    }

    public function testUploadFailureThrows(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Invalid package'], 422)]);
        $path = $this->mockPackage();

        try {
            $this->client()->upload($path);
            $this->fail('Expected H5PServiceException');
        } catch (H5PServiceException $e) {
            $this->assertSame(422, $e->getStatus());
            $this->assertStringContainsString('Invalid package', $e->getMessage());
        } finally {
            @unlink($path);
        }
    }

    public function testDownloadWritesPackageToTemporaryFile(): void
    {
        H5PServiceFake::fake();
        $content = H5PContentFactory::create(['title' => 'Exported']);

        $path = $this->client()->download($content->getKey());

        $this->assertFileExists($path);
        $this->assertStringEndsWith('.h5p', $path);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path));
        $this->assertSame('Exported', json_decode($zip->getFromName('h5p.json'), true)['title']);
        $zip->close();
        @unlink($path);
    }

    public function testDownloadOfMissingContentThrows(): void
    {
        H5PServiceFake::fake();
        $this->expectException(H5PServiceException::class);
        $this->client()->download(PHP_INT_MAX);
    }

    public function testShowAndDelete(): void
    {
        H5PServiceFake::fake();
        $content = H5PContentFactory::create(['title' => 'To delete']);

        $this->assertSame('To delete', $this->client()->show($content->getKey())['title']);
        $this->assertTrue($this->client()->delete($content->getKey()));
        $this->assertFalse($this->client()->delete($content->getKey()));
        $this->assertNull(H5PContent::query()->find($content->getKey()));
    }

    public function testDeleteServerErrorThrows(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'down'], 503)]);
        $this->expectException(H5PServiceException::class);
        $this->client()->delete(1);
    }

    public function testModelIsReadOnly(): void
    {
        $content = H5PContentFactory::create();
        $this->expectException(H5PContentReadOnlyException::class);
        $content->forceFill(['title' => 'changed'])->save();
    }

    public function testModelCannotBeDeleted(): void
    {
        $content = H5PContentFactory::create();
        $this->expectException(H5PContentReadOnlyException::class);
        $content->delete();
    }

    public function testTopicContentShape(): void
    {
        $content = H5PContentFactory::create(['title' => 'Quiz']);

        $this->assertSame([
            'id' => $content->getKey(),
            'title' => 'Quiz',
            'library' => 'H5P.MultiChoice 1.16',
            'main_library' => 'H5P.MultiChoice',
        ], $content->toTopicContent());
    }
}
