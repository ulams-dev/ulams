<?php

namespace Ulams\TemplatesPdf\Tests\Api;

use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Ulams\Core\Models\User as CoreUser;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\User;
use Ulams\Templates\Listeners\TemplateEventListener;
use Ulams\TemplatesPdf\Database\Seeders\TemplatesPdfSeeder;
use Ulams\TemplatesPdf\Models\FabricPDF;
use Ulams\TemplatesPdf\Services\Contracts\CertificateValidityContract;
use Ulams\TemplatesPdf\Tests\TestCase;

class CertificateExpiryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TemplatesPdfSeeder::class);
        config(['ulams_templates_pdf.storage.render_on_create' => false]);
        Notification::fake();
    }

    private function issue(Course $course, User $student): FabricPDF
    {
        app(TemplateEventListener::class)->handle(new CourseFinished(CoreUser::find($student->getKey()), $course));

        return FabricPDF::where('user_id', $student->getKey())->latest('id')->firstOrFail();
    }

    public function testCertificatesDoNotExpireByDefaultAndKeepTheCourseVersion(): void
    {
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $pdf = $this->issue($course, User::factory()->create());

        $this->assertNull($pdf->expires_at);
        $this->assertSame('', $pdf->vars['@VarCertificateExpiresAt']);
        $this->assertSame($course->updated_at->utc()->format('Y-m-d\TH:i:s\Z'), $pdf->course_version);
    }

    public function testConfiguredValiditySetsTheExpiryDateAndTheVariable(): void
    {
        config(['ulams_templates_pdf.validity_days' => 365]);
        $this->travelTo(now()->setDate(2026, 3, 1)->startOfDay());

        $pdf = $this->issue(Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]), User::factory()->create());

        $this->assertSame('2027-03-01', $pdf->expires_at->format('Y-m-d'));
        $this->assertSame('01.03.2027', $pdf->vars['@VarCertificateExpiresAt']);
        $this->assertSame('valid', $pdf->status());

        $this->travelTo(now()->addDays(366));
        $this->assertSame('expired', $pdf->fresh()->status());
    }

    public function testValidityCanBeReplacedPerCourse(): void
    {
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $this->app->bind(CertificateValidityContract::class, fn () => new class ($course->getKey()) implements CertificateValidityContract {
            public function __construct(private int $courseId)
            {
            }

            public function expiresAt(?string $assignableType, ?int $assignableId, CarbonInterface $issuedAt): ?CarbonInterface
            {
                return $assignableType === Course::class && $assignableId === $this->courseId ? $issuedAt->copy()->addDays(30) : null;
            }
        });

        $pdf = $this->issue($course, User::factory()->create());

        $this->assertSame(now()->addDays(30)->format('Y-m-d'), $pdf->expires_at->format('Y-m-d'));
    }

    public function testTheQrUrlPointsToTheLearnerSiteWhenOneIsConfigured(): void
    {
        config(['app.url' => 'https://api.acme.test']);
        $this->assertSame('https://api.acme.test/certificates/verify/abc-123', \Ulams\TemplatesPdf\Core\PdfVariables::verifyUrl('abc-123'));

        \Ulams\Settings\Models\Setting::query()->create(['key' => 'frontURL', 'group' => 'global', 'value' => 'https://learn.acme.test/', 'type' => 'text', 'public' => true, 'enumerable' => false]);
        $this->assertSame('https://learn.acme.test/certificates/verify/abc-123', \Ulams\TemplatesPdf\Core\PdfVariables::verifyUrl('abc-123'));
    }
}
