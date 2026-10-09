<?php

namespace Ulams\TemplatesPdf\Tests\Api;

use Ulams\Categories\Models\Category;
use Ulams\Core\Models\User as CoreUser;
use Ulams\Core\Tests\ApiTestTrait;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Models\User;
use Ulams\Courses\Tests\ProgressConfigurable;
use Ulams\Courses\ValueObjects\CourseProgressCollection;
use Ulams\Templates\Listeners\TemplateEventListener;
use Ulams\Templates\Models\Template;
use Ulams\TemplatesPdf\Core\PdfChannel;
use Ulams\TemplatesPdf\Courses\UserFinishedCourseVariables;
use Ulams\TemplatesPdf\Database\Seeders\TemplatesPdfSeeder;
use Ulams\TemplatesPdf\Events\PdfCreated;
use Ulams\TemplatesPdf\Models\FabricPDF;
use Ulams\TemplatesPdf\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Client\Request as HttpRequest;
use Ulams\TemplatesPdf\Pdfme\PdfmeTemplate;

class CoursesTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;
    use ProgressConfigurable;

    public function setUp(): void
    {
        parent::setUp();
        if (!class_exists(\Ulams\Courses\UlamsCourseServiceProvider::class)) {
            $this->markTestSkipped('Courses package not installed');
        }
        if (!class_exists(\Ulams\Scorm\UlamsScormServiceProvider::class)) {
            $this->markTestSkipped('Scorm package not installed');
        }
        $this->seed(TemplatesPdfSeeder::class);
    }

    public function testUserFinishedCourseNotification(): void
    {
        Notification::fake();
        Event::fake([
            CourseFinished::class,
            PdfCreated::class,
        ]);

        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'active_from' => Carbon::now()]);
        $course->categories()->attach(Category::factory()->create());
        $course->categories()->attach(Category::factory()->create());

        $lesson = Lesson::factory([
            'course_id' => $course->getKey()
        ])->create();
        $topics = Topic::factory(2)->create([
            'lesson_id' => $lesson->getKey(),
            'active' => true,
        ]);

        $student = User::factory([
            'points' => 0,
        ])->create();

        $courseProgress = CourseProgressCollection::make($student, $course);
        $this->assertFalse($courseProgress->isFinished());

        $student->courses()->attach($course->getKey());
        $this->response = $this->actingAs($student, 'api')->json(
            'PATCH',
            '/api/courses/progress/' . $course->getKey(),
            ['progress' => $this->getProgressUpdate($course)]
        );
        $courseProgress = CourseProgressCollection::make($student, $course);
        $this->response->assertOk();
        $this->assertTrue($courseProgress->isFinished());

        $user = CoreUser::find($student->getKey());

        Event::assertDispatched(CourseFinished::class);
        Event::assertDispatched(CourseFinished::class, function (CourseFinished $event) use ($user, $course) {
            return $event->getCourse()->getKey() === $course->getKey() && $event->getUser()->getKey() === $user->getKey();
        });

        Event::assertNotDispatched(PdfCreated::class);

        Log::listen(
            fn (MessageLogged $message) =>
            $this->assertNotEquals('error', $message->level, $message->message)
        );
        $listener = app(TemplateEventListener::class);
        $listener->handle(new CourseFinished($user, $course));

        Event::assertDispatched(PdfCreated::class);

        $template = Template::where('event', CourseFinished::class)->where('channel', PdfChannel::class)->where('default', true)->first();
        $pdf = FabricPDF::where('user_id', $user->getKey())->latest()->first();

        $section = $template->sections->where('key', 'title')->first();

        $this->assertEquals(str_replace(UserFinishedCourseVariables::VAR_COURSE_TITLE, $course->title, $section->content), $pdf->title);
        $this->assertEquals('1', $pdf->vars[UserFinishedCourseVariables::VAR_CONTINUOUS_CERT_NUMBER]);
        $this->assertEquals('1/' . now()->year, $pdf->vars[UserFinishedCourseVariables::VAR_ANNUAL_CERT_NUMBER]);
    }

    public function testFinishingCourseRendersCertificateThroughPdfService(): void
    {
        Storage::fake('local');
        config(['ulams_templates_pdf.storage.disk' => 'local']);
        $this->fakePdfService();
        Notification::fake();
        Event::fake([PdfCreated::class]);

        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'title' => 'Kurs "BHP" — łączność']);
        $lesson = Lesson::factory(['course_id' => $course->getKey()])->create();
        Topic::factory(2)->create(['lesson_id' => $lesson->getKey(), 'active' => true]);
        $student = User::factory(['points' => 0, 'first_name' => 'Zażółć', 'last_name' => 'Gęślą'])->create();

        // CourseFinished is not faked: the templates listener issues the certificate
        $student->courses()->attach($course->getKey());
        $this->actingAs($student, 'api')->json(
            'PATCH',
            '/api/courses/progress/' . $course->getKey(),
            ['progress' => $this->getProgressUpdate($course)]
        )->assertOk();

        Event::assertDispatched(PdfCreated::class);
        $pdf = FabricPDF::where('user_id', $student->getKey())->latest()->firstOrFail();

        // the stored template is the pdfme default certificate, variables are kept separately
        $this->assertTrue(PdfmeTemplate::isPdfme(PdfmeTemplate::decode($pdf->content)));
        $this->assertEquals($course->title, $pdf->vars[UserFinishedCourseVariables::VAR_COURSE_TITLE]);
        $this->assertNotEmpty($pdf->certificate_id);
        $this->assertEquals($pdf->certificate_id, $pdf->vars[UserFinishedCourseVariables::VAR_CERTIFICATE_ID]);
        $this->assertStringEndsWith('/certificates/verify/' . $pdf->certificate_id, $pdf->vars[UserFinishedCourseVariables::VAR_CERTIFICATE_VERIFY_URL]);

        // rendered once through the service, with the internal token and the variables as inputs
        Http::assertSent(function (HttpRequest $request) use ($student, $course, $pdf) {
            $inputs = $request->data()['inputs'][0] ?? [];
            return $request->url() === TestCase::PDF_SERVICE . '/render'
                && $request->hasHeader('X-Internal-Token', TestCase::PDF_TOKEN)
                && ($inputs['@VarUserName'] ?? null) === $student->name
                && ($inputs['@VarCourseTitle'] ?? null) === $course->title
                && ($inputs['@VarCertificateVerifyUrl'] ?? null) === $pdf->vars['@VarCertificateVerifyUrl']
                && isset($request->data()['template']['schemas']);
        });
        $this->assertNotNull($pdf->path);
        Storage::disk('local')->assertExists($pdf->path);

        // the learner downloads the stored file (no second render)
        $response = $this->actingAs($student, 'api')->get('/api/pdfs/generate/' . $pdf->getKey());
        $response->assertOk();
        $response->assertDownload();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Zażółć Gęślą', $response->getContent());
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        Http::assertSentCount(1);

        // another learner cannot download it
        $this->actingAs(User::factory()->create(), 'api')->get('/api/pdfs/generate/' . $pdf->getKey())->assertForbidden();
    }

    public function testCertificateIsIssuedWhenRendererIsDownAndRenderedOnDownload(): void
    {
        Storage::fake('local');
        config(['ulams_templates_pdf.storage.disk' => 'local']);
        $this->fakePdfService(503);
        Notification::fake();
        Event::fake([CourseFinished::class]);

        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $student = User::factory()->create();

        app(TemplateEventListener::class)->handle(new CourseFinished(CoreUser::find($student->getKey()), $course));

        $pdf = FabricPDF::where('user_id', $student->getKey())->latest()->firstOrFail();
        $this->assertNull($pdf->path);

        $this->actingAs($student, 'api')->get('/api/pdfs/generate/' . $pdf->getKey())
            ->assertStatus(503)
            ->assertJsonFragment(['error' => 'render_failed']);

        $this->fakePdfService();
        $this->actingAs($student, 'api')->get('/api/pdfs/generate/' . $pdf->getKey())
            ->assertOk()
            ->assertDownload();
    }
}
