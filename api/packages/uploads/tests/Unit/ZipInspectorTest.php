<?php

namespace Ulams\Uploads\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Ulams\Uploads\Exceptions\UploadRejected;
use Ulams\Uploads\Tests\TestCase;
use Ulams\Uploads\Zip\ZipInspector;
use Ulams\Uploads\Zip\ZipLimits;

class ZipInspectorTest extends TestCase
{
    public function testAcceptsAValidPackageAndNormalisesPaths(): void
    {
        $zip = $this->makeZip([
            'imsmanifest.xml' => '<manifest/>',
            'assets/' => '',
            './assets/./app.js' => 'console.log(1)',
        ]);

        $entries = (new ZipInspector())->inspect($zip, new ZipLimits());

        $this->assertSame(['imsmanifest.xml', 'assets', 'assets/app.js'], array_map(fn ($e) => $e->path, $entries));
        $this->assertTrue($entries[1]->isDirectory);
    }

    public static function hostileNames(): array
    {
        return [
            'zip-slip' => ['../../etc/cron.d/evil', 'zip_slip'],
            'zip-slip in the middle' => ['assets/../../evil.php', 'zip_slip'],
            'absolute path' => ['/var/www/html/public/evil.php', 'absolute_path'],
            'drive letter' => ['C:/Windows/evil.dll', 'absolute_path'],
            'backslash' => ['..\\..\\evil.php', 'zip_slip'],
        ];
    }

    #[DataProvider('hostileNames')]
    public function testRejectsHostileEntryNames(string $name, string $reason): void
    {
        $zip = $this->makeZip(['imsmanifest.xml' => '<manifest/>', $name => 'x']);

        $this->assertRejected($reason, fn () => (new ZipInspector())->inspect($zip, new ZipLimits()));
    }

    public function testLegacyCourseExportsMayHaveALeadingSlash(): void
    {
        $zip = $this->makeZip(['/content.json' => '{}', '/categories/icon.svg' => '<svg/>']);

        $entries = (new ZipInspector())->inspect($zip, ZipLimits::fromConfig('course-import'));

        $this->assertSame(['content.json', 'categories/icon.svg'], array_map(fn ($e) => $e->path, $entries));
        $this->assertRejected('absolute_path', fn () => (new ZipInspector())->inspect($zip, ZipLimits::fromConfig('package')));
        $this->assertRejected('zip_slip', fn () => (new ZipInspector())->inspect(
            $this->makeZip(['/../escape' => 'x']),
            ZipLimits::fromConfig('course-import')
        ));
    }

    public function testRejectsSymlinks(): void
    {
        $zip = $this->makeZip(['imsmanifest.xml' => '<manifest/>'], ['link' => '/etc/passwd']);

        $this->assertRejected('symlink', fn () => (new ZipInspector())->inspect($zip, new ZipLimits()));
    }

    public function testRejectsTooManyEntries(): void
    {
        $entries = [];
        for ($i = 0; $i < 51; $i++) {
            $entries["f{$i}.txt"] = 'x';
        }
        $zip = $this->makeZip($entries);

        $this->assertRejected('too_many_entries', fn () => (new ZipInspector())->inspect($zip, new ZipLimits(maxEntries: 50)));
    }

    public function testRejectsAHighCompressionRatio(): void
    {
        $zip = $this->makeZipBomb(8);

        $this->assertRejected('zip_bomb', fn () => (new ZipInspector())->inspect($zip, new ZipLimits(maxRatio: 100)));
    }

    public function testRejectsATotalUncompressedSizeOverTheLimit(): void
    {
        $zip = $this->makeZip(['a.txt' => random_bytes(600 * 1024), 'b.txt' => random_bytes(600 * 1024)]);

        $this->assertRejected('zip_bomb', fn () => (new ZipInspector())->inspect($zip, new ZipLimits(maxUncompressed: 1024 * 1024)));
    }

    public function testRejectsDuplicateNormalisedPaths(): void
    {
        $zip = $this->makeZip(['a/b.txt' => '1', 'a/./b.txt' => '2']);

        $this->assertRejected('duplicate_entry', fn () => (new ZipInspector())->inspect($zip, new ZipLimits()));
    }

    public function testRejectsAFileThatIsNotAZip(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ulams-notzip-');
        file_put_contents($path, 'not a zip');

        try {
            $this->assertRejected('invalid_archive', fn () => (new ZipInspector())->inspect($path, new ZipLimits()));
        } finally {
            unlink($path);
        }
    }

    private function assertRejected(string $reason, callable $callback): void
    {
        try {
            $callback();
        } catch (UploadRejected $e) {
            $this->assertSame($reason, $e->reason, $e->getMessage());

            return;
        }
        $this->fail("Expected the upload to be rejected with [{$reason}].");
    }
}
