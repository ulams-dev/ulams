<?php

namespace Ulams\Uploads\Tests\Unit;

use Illuminate\Support\Facades\Storage;
use Ulams\Uploads\Exceptions\UploadRejected;
use Ulams\Uploads\Tests\TestCase;
use Ulams\Uploads\Zip\SafeExtractor;
use Ulams\Uploads\Zip\ZipEntry;
use Ulams\Uploads\Zip\ZipInspector;
use Ulams\Uploads\Zip\ZipLimits;

class SafeExtractorTest extends TestCase
{
    public function testExtractsEntriesUnderThePrefix(): void
    {
        Storage::fake('pkg');
        $zip = $this->makeZip(['index.html' => '<p>hi</p>', 'js/' => '', 'js/app.js' => 'run()']);

        $written = app(SafeExtractor::class)->extractToDisk($zip, Storage::disk('pkg'), 'scorm/1.2/abc');

        $this->assertSame(['scorm/1.2/abc/index.html', 'scorm/1.2/abc/js/app.js'], $written);
        $this->assertSame('run()', Storage::disk('pkg')->get('scorm/1.2/abc/js/app.js'));
    }

    public function testRejectsZipSlipAndWritesNothing(): void
    {
        Storage::fake('pkg');
        $zip = $this->makeZip(['index.html' => 'ok', '../../escape.txt' => 'evil']);

        $this->expectException(UploadRejected::class);
        try {
            app(SafeExtractor::class)->extractToDisk($zip, Storage::disk('pkg'), 'scorm/x');
        } finally {
            $this->assertSame([], Storage::disk('pkg')->allFiles());
        }
    }

    public function testCountsRealBytesWhenTheCentralDirectoryLiesAndCleansUp(): void
    {
        Storage::fake('pkg');
        $zip = $this->makeZip(['a.txt' => 'small', 'b.txt' => str_repeat('b', 64 * 1024)]);

        // An inspector that believes forged headers: every entry claims 5 bytes.
        $lyingInspector = new class () extends ZipInspector {
            public function inspectOpen(\ZipArchive $zip, ZipLimits $limits): array
            {
                return array_map(
                    fn (ZipEntry $e) => new ZipEntry($e->index, $e->name, $e->path, $e->isDirectory, 5, 5),
                    parent::inspectOpen($zip, $limits)
                );
            }
        };

        try {
            (new SafeExtractor($lyingInspector))->extractToDisk($zip, Storage::disk('pkg'), 'p');
            $this->fail('Expected the extraction to stop at the declared size.');
        } catch (UploadRejected $e) {
            $this->assertSame('zip_bomb', $e->reason);
        }
        // a.txt was written before b.txt failed, and was removed again
        $this->assertSame([], Storage::disk('pkg')->allFiles());
    }

    public function testExtractsToALocalDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/ulams-extract-' . bin2hex(random_bytes(4));
        $zip = $this->makeZip(['content.json' => '{}', 'files/a.txt' => 'a']);

        try {
            $written = app(SafeExtractor::class)->extractToDirectory($zip, $dir);

            $this->assertSame(['content.json', 'files/a.txt'], $written);
            $this->assertFileExists($dir . '/files/a.txt');
        } finally {
            Storage::build(['driver' => 'local', 'root' => sys_get_temp_dir()])->deleteDirectory(basename($dir));
        }
    }

    public function testDoesNotFollowSymlinks(): void
    {
        Storage::fake('pkg');
        $zip = $this->makeZip(['index.html' => 'ok'], ['passwd' => '/etc/passwd']);

        $this->expectException(UploadRejected::class);
        app(SafeExtractor::class)->extractToDisk($zip, Storage::disk('pkg'), 'p');
    }
}
