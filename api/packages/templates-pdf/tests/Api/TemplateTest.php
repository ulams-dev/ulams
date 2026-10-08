<?php

namespace Ulams\TemplatesPdf\Tests\Api;

use Ulams\Core\Tests\ApiTestTrait;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Templates\Database\Seeders\PermissionTableSeeder;
use Ulams\Templates\Events\ManuallyTriggeredEvent;
use Ulams\Templates\Listeners\TemplateEventListener;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Models\TemplateSection;
use Ulams\TemplatesPdf\Core\PdfChannel;
use Ulams\TemplatesPdf\Core\UserVariables;
use Ulams\TemplatesPdf\Events\PdfCreated;
use Ulams\TemplatesPdf\Models\FabricPDF;
use Ulams\TemplatesPdf\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Event;

class TemplateTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionTableSeeder::class);
    }

    public function testManuallyTriggeredEvent(): void
    {
        Event::fake([
            ManuallyTriggeredEvent::class,
            PdfCreated::class,
        ]);

        $template = Template::factory()->create([
            'name' => 'Pdf',
            'channel' => PdfChannel::class,
            'event' => ManuallyTriggeredEvent::class,
            'default' => true,
        ]);

        $titleSection = TemplateSection::factory()->create([
            'key' => 'title',
            'content' => 'Pdf for @VarUserName',
            'template_id' => $template->getKey()
        ]);

        $contentSection = TemplateSection::factory()->create([
            'key' => 'content',
            'content' => '{"version":"4.6.0","objects":[]}',
            'template_id' => $template->getKey()
        ]);

        $student = $this->makeStudent();
        $admin = $this->makeAdmin();

        $this->response = $this->actingAs($admin, 'api')->postJson(
            '/api/admin/events/trigger-manually/' . $template->getKey(),
            ['users' => [$student->getKey()]]
        )->assertOk();

        $listener = app(TemplateEventListener::class);
        $listener->handle(new ManuallyTriggeredEvent($student));
        Event::assertDispatched(PdfCreated::class);
        $pdf = FabricPDF::where('user_id', $student->getKey())->latest()->first();
        $this->assertEquals(str_replace(UserVariables::VAR_USER_NAME, $student->name, $titleSection->content), $pdf->title);
    }
}
