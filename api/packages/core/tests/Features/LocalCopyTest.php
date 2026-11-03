<?php

namespace Ulams\Core\Tests\Features;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Ulams\Core\Storage\LocalCopy;
use Ulams\Core\Tests\Mocks\FakeS3;
use Ulams\Core\Tests\TestCase;

class LocalCopyTest extends TestCase
{
    private function leftovers(): array
    {
        return glob(sys_get_temp_dir() . '/ulams-copy-*') ?: [];
    }

    public function testALocalDiskHandsOutTheFileItself(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('docs/a.pdf', 'local bytes');

        $seen = LocalCopy::with('local', 'docs/a.pdf', function (string $path) {
            $this->assertSame(Storage::disk('local')->path('docs/a.pdf'), $path);

            return file_get_contents($path);
        });

        $this->assertSame('local bytes', $seen);
        Storage::disk('local')->assertExists('docs/a.pdf');
    }

    public function testAnS3ObjectIsCopiedToATemporaryFileThatIsRemovedAfterwards(): void
    {
        $s3 = FakeS3::install();
        Storage::put('course/1/doc.pdf', 'remote bytes');
        $before = $this->leftovers();

        $copied = LocalCopy::with(Storage::disk(), 'course/1/doc.pdf', function (string $path) {
            $this->assertFileExists($path);
            $this->assertStringEndsWith('.pdf', $path, 'libraries that sniff the extension keep working');
            $this->assertNotSame(Storage::path('course/1/doc.pdf'), $path, 'Storage::path() on S3 is only the key');

            return [$path, file_get_contents($path)];
        });

        $this->assertSame('remote bytes', $copied[1]);
        $this->assertFileDoesNotExist($copied[0]);
        $this->assertSame($before, $this->leftovers());
        $this->assertContains('GetObject', $s3->commands);
        $this->assertArrayHasKey('course/1/doc.pdf', $s3->objects, 'the object itself is untouched');
    }

    public function testTheTemporaryFileIsRemovedWhenTheCallbackThrows(): void
    {
        FakeS3::install();
        Storage::put('a.zip', 'zip');
        $before = $this->leftovers();

        try {
            LocalCopy::with(null, 'a.zip', function (string $path) {
                throw new RuntimeException('the library failed');
            });
            $this->fail('the exception must pass through');
        } catch (RuntimeException $exception) {
            $this->assertSame('the library failed', $exception->getMessage());
        }

        $this->assertSame($before, $this->leftovers());
    }

    public function testAMissingObjectThrowsAndLeavesNothingBehind(): void
    {
        FakeS3::install();
        $before = $this->leftovers();

        try {
            LocalCopy::with(null, 'nope/missing.png', fn (string $path) => 'unreachable');
            $this->fail('a missing object must throw');
        } catch (FileNotFoundException|\League\Flysystem\UnableToReadFile $exception) {
            $this->assertTrue(true);
        }

        $this->assertSame($before, $this->leftovers());
    }

    public function testAMissingLocalFileThrows(): void
    {
        Storage::fake('local');

        $this->expectException(FileNotFoundException::class);
        LocalCopy::with('local', 'nope.txt', fn (string $path) => 'unreachable');
    }

    public function testTheExtensionCanBeForced(): void
    {
        FakeS3::install();
        Storage::put('blob', 'x');

        $name = LocalCopy::with(null, 'blob', fn (string $path) => basename($path), 'zip');

        $this->assertStringEndsWith('.zip', $name);
    }

    public function testIsLocalTellsTheDisksApart(): void
    {
        Storage::fake('local');
        FakeS3::install();

        $this->assertTrue(LocalCopy::isLocal(Storage::disk('local')));
        $this->assertFalse(LocalCopy::isLocal(Storage::disk('s3')));
    }
}
