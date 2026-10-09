<?php

namespace Ulams\Uploads\Tests\Unit;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Ulams\Uploads\Exceptions\UploadRejected;
use Ulams\Uploads\Rules\SafeUpload;
use Ulams\Uploads\Scanning\VirusScannerContract;
use Ulams\Uploads\Tests\TestCase;
use Ulams\Uploads\UploadGuard;

class UploadGuardTest extends TestCase
{
    public function testAcceptsAValidScormPackage(): void
    {
        app(UploadGuard::class)->check($this->upload($this->makeScormZip(), 'course.zip'), 'scorm');

        $this->addToAssertionCount(1);
    }

    public function testRejectsAWrongExtension(): void
    {
        $this->assertReason('wrong_type', $this->upload($this->makeScormZip(), 'course.tar'), 'scorm');
    }

    public function testRejectsContentThatIsNotAZipDespiteTheExtension(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ulams-html-');
        file_put_contents($path, '<!doctype html><script>alert(1)</script>');

        try {
            $this->assertReason('wrong_type', $this->upload($path, 'course.zip'), 'scorm');
        } finally {
            unlink($path);
        }
    }

    public function testRejectsFilesOverTheSizeLimit(): void
    {
        config(['ulams_uploads.policies.scorm.max_size' => 100]);

        $this->assertReason('too_large', $this->upload($this->makeScormZip(), 'course.zip'), 'scorm');
    }

    public function testRunsTheZipInspector(): void
    {
        $zip = $this->makeScormZip(['../../../../var/www/html/public/x.php' => '<?php echo 1;']);

        $this->assertReason('zip_slip', $this->upload($zip, 'course.zip'), 'scorm');
    }

    public function testCallsTheVirusScannerBeforeInspecting(): void
    {
        $this->app->instance(VirusScannerContract::class, new class () implements VirusScannerContract {
            public function scan(string $path): void
            {
                throw new UploadRejected('infected', 'The file was rejected by the virus scanner (Eicar-Test-Signature).');
            }
        });
        $this->app->forgetInstance(UploadGuard::class);

        $this->assertReason('infected', $this->upload($this->makeScormZip(), 'course.zip'), 'scorm');
    }

    public function testValidationRuleReportsTheReason(): void
    {
        $zip = $this->makeScormZip([], ['evil' => '/etc/passwd']);

        $validator = Validator::make(
            ['zip' => $this->upload($zip, 'course.zip')],
            ['zip' => ['required', new SafeUpload('scorm')]]
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('symbolic link', $validator->errors()->first('zip'));
    }

    private function upload(string $path, string $name): UploadedFile
    {
        return new UploadedFile($path, $name, null, null, true);
    }

    private function assertReason(string $reason, UploadedFile $file, string $kind): void
    {
        try {
            app(UploadGuard::class)->check($file, $kind);
        } catch (UploadRejected $e) {
            $this->assertSame($reason, $e->reason, $e->getMessage());

            return;
        }
        $this->fail("Expected [{$reason}].");
    }
}
