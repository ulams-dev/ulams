<?php

namespace Ulams\TemplatesPdf\Tests\Feature;

use Ulams\Core\Tests\ApiTestTrait;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Templates\Facades\Template;
use Ulams\Templates\Repository\Contracts\TemplateRepositoryContract;
use Ulams\TemplatesPdf\Core\PdfChannel;
use Ulams\TemplatesPdf\Database\Seeders\TemplatesPdfSeeder;
use Ulams\TemplatesPdf\Tests\Mocks\TestEvent;
use Ulams\TemplatesPdf\Tests\Mocks\TestVariables;
use Ulams\TemplatesPdf\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class PdfChannelTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        Template::register(TestEvent::class, PdfChannel::class, TestVariables::class);
        $this->seed(TemplatesPdfSeeder::class);
    }

    public function testPreview()
    {
        Event::fake();
        Notification::fake();

        $admin = $this->makeAdmin();

        $template = app(TemplateRepositoryContract::class)->findTemplateDefault(TestEvent::class, PdfChannel::class);

        $preview = Template::sendPreview($admin, $template);

        $this->assertTrue($preview->toArray()['sent']);
    }
}
