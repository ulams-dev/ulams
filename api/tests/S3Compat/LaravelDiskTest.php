<?php

namespace Tests\S3Compat;

use Aws\S3\S3Client;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Ulams\Core\Storage\LocalCopy;
use Ulams\Core\Tests\TestCase;

/**
 * The Laravel `s3` disk (Flysystem adapter, as `config/filesystems.php` builds it) against a real object store:
 * what topic content, the images service, SCORM and the file manager do through it (ADR 0041). Opt-in like
 * S3CompatibilityTest (S3_COMPAT=1). Throws are off, as in production, so a refusal shows as false.
 */
class LaravelDiskTest extends TestCase
{
    private Filesystem $disk;
    private S3Client $client;
    private string $bucket;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('S3_COMPAT') !== '1') {
            $this->markTestSkipped('Set S3_COMPAT=1 (see deploy/scripts/s3-compat.sh) to run against a real object store.');
        }

        $this->bucket = 'laraveldisk-' . bin2hex(random_bytes(4));
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => (string) getenv('S3_COMPAT_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => (string) getenv('S3_COMPAT_KEY'), 'secret' => (string) getenv('S3_COMPAT_SECRET')],
        ]);
        $this->client->createBucket(['Bucket' => $this->bucket]);
        $this->disk = Storage::build([
            'driver' => 's3',
            'key' => getenv('S3_COMPAT_KEY'),
            'secret' => getenv('S3_COMPAT_SECRET'),
            'region' => 'us-east-1',
            'bucket' => $this->bucket,
            'url' => getenv('S3_COMPAT_ENDPOINT') . '/' . $this->bucket,
            'endpoint' => getenv('S3_COMPAT_ENDPOINT'),
            'use_path_style_endpoint' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (getenv('S3_COMPAT') === '1' && isset($this->client)) {
            try {
                $this->client->deleteMatchingObjects($this->bucket, '', '/.*/s');
                $this->client->deleteBucket(['Bucket' => $this->bucket]);
            } catch (\Throwable) {
            }
        }
        parent::tearDown();
    }

    public function testFilesRoundTripThroughTheDisk(): void
    {
        $this->assertTrue($this->disk->put('course/1/topic/2/notes.txt', 'hello', 'public'));
        $this->assertTrue($this->disk->exists('course/1/topic/2/notes.txt'));
        $this->assertSame('hello', $this->disk->get('course/1/topic/2/notes.txt'));
        $this->assertSame(5, $this->disk->size('course/1/topic/2/notes.txt'));
        $this->assertSame('text/plain', $this->disk->mimeType('course/1/topic/2/notes.txt'));
        // not asserted: SeaweedFS accepts the public-read ACL and ignores it (GetObjectAcl shows the owner only);
        // public access comes from the bucket policy
        $this->assertTrue($this->disk->copy('course/1/topic/2/notes.txt', 'course/1/topic/2/copy.txt'));
        $this->assertTrue($this->disk->move('course/1/topic/2/copy.txt', 'course/1/topic/2/moved.txt'));
        $this->assertFalse($this->disk->exists('course/1/topic/2/copy.txt'));
        $this->assertEqualsCanonicalizing(['course/1/topic/2/notes.txt', 'course/1/topic/2/moved.txt'], $this->disk->files('course/1/topic/2'));
        $this->assertSame($this->bucket . '/course/1/topic/2/notes.txt', ltrim(parse_url($this->disk->url('course/1/topic/2/notes.txt'), PHP_URL_PATH), '/'));
    }

    public function testDirectoriesAreKeyPrefixes(): void
    {
        $this->disk->put('directory/a.txt', 'a');
        $this->disk->put('directory/nested/b.txt', 'b');
        $this->disk->put('other/c.txt', 'c');

        $this->assertTrue($this->disk->directoryExists('directory'));
        $this->assertFalse($this->disk->directoryExists('missing'));
        $this->assertTrue($this->disk->deleteDirectory('directory'));
        $this->assertSame([], $this->disk->allFiles('directory'));
        $this->assertSame(['other/c.txt'], $this->disk->allFiles());
    }

    public function testAPdfIsParsedFromACopyOfTheObject(): void
    {
        $bytes = (string) file_get_contents(__DIR__ . '/../../packages/topic-types/tests/mocks/pdf.pdf');
        $this->disk->put('course/1/topic/2/pdf/doc.pdf', $bytes);

        $pages = LocalCopy::with($this->disk, 'course/1/topic/2/pdf/doc.pdf', fn (string $path) => count((new Parser())->parseFile($path)->getPages()));

        $this->assertGreaterThanOrEqual(1, $pages);
        $this->assertSame([], glob(sys_get_temp_dir() . '/ulams-copy-*') ?: []);
    }

    public function testAnImageIsSizedFromAStoredObject(): void
    {
        $image = imagecreatetruecolor(40, 30);
        ob_start();
        imagepng($image);
        $this->disk->put('course/1/image.png', (string) ob_get_clean());

        $size = @getimagesizefromstring((string) $this->disk->get('course/1/image.png'));
        $viaFile = LocalCopy::with($this->disk, 'course/1/image.png', fn (string $path) => getimagesize($path));

        $this->assertSame([40, 30], [$size[0], $size[1]]);
        $this->assertSame([40, 30], [$viaFile[0], $viaFile[1]]);
    }

    public function testAStreamUploadAndAStreamedRead(): void
    {
        $source = fopen('php://temp', 'w+b');
        fwrite($source, random_bytes(2 * 1024 * 1024));
        rewind($source);
        $expected = md5(stream_get_contents($source));
        rewind($source);

        $this->assertTrue($this->disk->writeStream('big/file.bin', $source));
        $stream = $this->disk->readStream('big/file.bin');

        $this->assertSame($expected, md5(stream_get_contents($stream)));
    }

    public function testAMissingObjectIsAbsentNotAnError(): void
    {
        $this->assertFalse($this->disk->exists('nope.txt'));
        $this->assertNull($this->disk->get('nope.txt'));
    }

    public function testVisibilityOfATopLevelKeyCanBeChanged(): void
    {
        if (strtolower((string) getenv('S3_COMPAT_FLAVOR')) === 'minio') {
            $this->markTestSkipped('MinIO answers 501 to PutObjectAcl.');
        }
        $this->disk->put('seg.ts', 'ts');

        $this->assertTrue($this->disk->setVisibility('seg.ts', 'public'));
    }
}
