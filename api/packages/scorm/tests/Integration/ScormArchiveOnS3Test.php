<?php

namespace Ulams\Scorm\Tests\Integration;

use Illuminate\Support\Facades\Storage;
use Peopleaps\Scorm\Exception\StorageNotFoundException;
use Peopleaps\Scorm\Manager\ScormDisk;
use Ulams\Core\Tests\Mocks\FakeS3;
use Ulams\Scorm\Tests\TestCase;
use ZipArchive;

/**
 * The SCORM library needs a file path for the uploaded archive. With the archive on S3 that is a temporary
 * copy, removed afterwards (ADR 0041); `Storage::path()` is only the object key there.
 */
class ScormArchiveOnS3Test extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('scorm.archive', 's3');
    }

    private function zip(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'scormzip');
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('imsmanifest.xml', '<manifest/>');
        $zip->close();
        $bytes = (string) file_get_contents($file);
        unlink($file);

        return $bytes;
    }

    public function testTheArchiveIsHandedOverAsALocalZipAndTheCopyIsRemoved(): void
    {
        $s3 = FakeS3::install();
        Storage::put('scorm/archive/package.zip', $this->zip());
        $seen = null;

        (new ScormDisk())->readScormArchive('scorm/archive/package.zip', function (string $path) use (&$seen) {
            $zip = new ZipArchive();
            $seen = ['path' => $path, 'opened' => $zip->open($path) === true && $zip->locateName('imsmanifest.xml') !== false];
        });

        $this->assertTrue($seen['opened']);
        $this->assertFileDoesNotExist($seen['path']);
        $this->assertArrayHasKey('scorm/archive/package.zip', $s3->objects, 'the archive on the bucket is not deleted');
    }

    public function testAMissingArchiveIsReportedAsNotFound(): void
    {
        FakeS3::install();

        $this->expectException(StorageNotFoundException::class);
        (new ScormDisk())->readScormArchive('scorm/archive/missing.zip', fn (string $path) => null);
    }
}
