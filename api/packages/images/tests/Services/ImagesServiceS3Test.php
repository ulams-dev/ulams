<?php

namespace Ulams\Images\Tests\Services;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Ulams\Core\Tests\Mocks\FakeS3;
use Ulams\Images\Enum\ConstantEnum;
use Ulams\Images\Services\Contracts\ImagesServiceContract;
use Ulams\Images\Tests\TestCase;

/**
 * Resized images are cached on the disk. On S3 `Storage::path()` is only an object key, so the service
 * must neither write placeholders under it nor expect to optimise a file on this machine (ADR 0041).
 */
class ImagesServiceS3Test extends TestCase
{
    use DatabaseTransactions;

    private FakeS3 $s3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s3 = FakeS3::install();
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public function testAResizedImageIsCachedOnTheBucketWithoutStrayObjects(): void
    {
        Storage::put('course/1/photo.png', $this->png(100, 80), 'public');

        $result = $this->app->make(ImagesServiceContract::class)->render('course/1/photo.png', ['w' => 50]);

        $this->assertStringStartsWith(ConstantEnum::CACHE_DIRECTORY . '/', $result['path']);
        $this->assertArrayHasKey($result['path'], $this->s3->objects);
        $this->assertSame('public-read', $this->s3->objects[$result['path']]['acl']);
        $size = getimagesizefromstring($this->s3->objects[$result['path']]['body']);
        $this->assertNotFalse($size);
        $this->assertLessThanOrEqual(100, $size[0]);
        $this->assertSame(
            ['course/1/photo.png', $result['path']],
            array_values(array_filter(array_keys($this->s3->objects), fn (string $key) => !str_ends_with($key, '/'))),
            'no placeholder under a local-looking path'
        );
    }

    public function testASourceThatIsMissingYieldsTheErrorImageInsteadOfAnException(): void
    {
        $result = $this->app->make(ImagesServiceContract::class)->render('course/1/missing.png', ['w' => 50]);

        $this->assertStringEndsWith('_error.svg', $result['path']);
        $this->assertArrayHasKey($result['path'], $this->s3->objects);
    }
}
