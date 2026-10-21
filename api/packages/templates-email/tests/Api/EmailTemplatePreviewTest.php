<?php

namespace Ulams\TemplatesEmail\Tests\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use Qferrer\Mjml\Exception\CurlException;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Templates\Database\Seeders\PermissionTableSeeder;
use Ulams\Templates\Events\ManuallyTriggeredEvent;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Models\TemplateSection;
use Ulams\TemplatesEmail\Core\EmailMailable;
use Ulams\TemplatesEmail\Services\Contracts\MjmlServiceContract;
use Ulams\TemplatesEmail\Services\MjmlService;
use Ulams\TemplatesEmail\Tests\TestCase;

class EmailTemplatePreviewTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private const MJML = '<mjml lang="en"><mj-head><mj-title>Hi</mj-title></mj-head><mj-body><mj-section><mj-column>'
        . '<mj-text>Hello @VarUserName from @VarAppName</mj-text></mj-column></mj-section></mj-body></mjml>';

    public function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionTableSeeder::class);
    }

    private function body(array $extra = []): array
    {
        return array_merge([
            'event' => ManuallyTriggeredEvent::class,
            'title' => 'Hello @VarUserName',
            'content' => self::MJML,
        ], $extra);
    }

    public function testRenderFillsTheSampleValuesAndStoresNothing(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        config(['app.name' => 'Sample Academy']);
        $templates = Template::count();
        $sections = TemplateSection::count();

        $data = $this->actingAs($admin, 'api')
            ->postJson('/api/admin/templates/email/render', $this->body(['sample_overrides' => ['@VarUserName' => 'Ada <b>']]))
            ->assertOk()
            ->json('data');

        $this->assertSame('Hello Ada <b>', $data['title']);
        $this->assertStringContainsString('Hello Ada &lt;b&gt; from Sample Academy', $data['html']);
        $this->assertSame([], $data['errors']);
        $this->assertSame([], $data['missing_variables']);
        $this->assertIsArray($data['warnings']);
        $this->assertSame($templates, Template::count());
        $this->assertSame($sections, TemplateSection::count());
        Mail::assertNothingSent();
    }

    public function testRenderUsesTheRegisteredSampleValuesWithoutOverrides(): void
    {
        $data = $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/templates/email/render', $this->body())
            ->assertOk()
            ->json('data');

        $this->assertStringNotContainsString('@VarUserName', $data['html']);
        $this->assertStringNotContainsString('@VarUserName', $data['title']);
    }

    public function testHtmlDraftsAreAccepted(): void
    {
        $data = $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/templates/email/render', $this->body(['content' => '<p>Hi @VarUserName <script>alert(1)</script></p>']))
            ->assertOk()
            ->json('data');

        $this->assertStringNotContainsString('<script>', $data['html']);
        $this->assertStringContainsString('<p>Hi ', $data['html']);
    }

    public function testMissingRequiredVariablesAreReportedNotRejected(): void
    {
        $data = $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/templates/email/render', $this->body(['title' => 'No variable', 'content' => '<mjml><mj-body></mj-body></mjml>']))
            ->assertOk()
            ->json('data');

        $this->assertSame(['@VarUserName'], $data['missing_variables']);
    }

    public function testMjmlErrorsAndAccessibilityWarningsAreReturned(): void
    {
        $this->instance(MjmlServiceContract::class, Mockery::mock(MjmlService::class, function (MockInterface $mock) {
            $mock->shouldReceive('renderWithErrors')->once()->andReturn([
                'html' => '<html><body><table><tr><td><img src="a.png"></td></tr></table></body></html>',
                'errors' => ['line 3: Attribute foo is illegal (mj-text)'],
            ]);
        }));

        $data = $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/templates/email/render', $this->body())
            ->assertOk()
            ->json('data');

        $this->assertSame(['line 3: Attribute foo is illegal (mj-text)'], $data['errors']);
        $codes = array_column($data['warnings'], 'code');
        foreach (['missing-lang', 'missing-title', 'image-missing-alt', 'layout-table-without-role'] as $code) {
            $this->assertContains($code, $codes);
        }
    }

    public function testRendererOutageIsA503(): void
    {
        $this->instance(MjmlServiceContract::class, Mockery::mock(MjmlService::class, function (MockInterface $mock) {
            $mock->shouldReceive('renderWithErrors')->andThrow(new CurlException('Curl Error: "Operation timed out"'));
        }));

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/templates/email/render', $this->body())
            ->assertStatus(503);
    }

    public function testValidation(): void
    {
        $admin = $this->makeAdmin();
        $url = '/api/admin/templates/email/render';

        $this->actingAs($admin, 'api')->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['event', 'title', 'content']);
        $this->actingAs($admin, 'api')->postJson($url, $this->body(['event' => 'App\\Nope']))->assertUnprocessable()->assertJsonValidationErrors(['event']);
        $this->actingAs($admin, 'api')->postJson($url, $this->body(['content' => str_repeat('a', 512 * 1024 + 1)]))
            ->assertUnprocessable()->assertJsonValidationErrors(['content']);
        // sample_overrides are limited to the variables of the event, and to 500 characters.
        $this->actingAs($admin, 'api')->postJson($url, $this->body(['sample_overrides' => ['@VarNotOfThisEvent' => 'x']]))
            ->assertUnprocessable()->assertJsonValidationErrors(['sample_overrides']);
        $this->actingAs($admin, 'api')->postJson($url, $this->body(['sample_overrides' => ['@VarUserName' => str_repeat('a', 501)]]))
            ->assertUnprocessable();
        $this->actingAs($admin, 'api')->postJson($url, $this->body(['sample_overrides' => ['@VarUserName' => 'ok']]))->assertOk();
    }

    public function testTestSendGoesOnlyToTheSignedInAdmin(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        $data = $this->actingAs($admin, 'api')
            ->postJson('/api/admin/templates/email/test-send', $this->body([
                'to' => 'victim@example.com',
                'email' => 'victim@example.com',
                'recipient' => 'victim@example.com',
                'cc' => [$student->email],
                'bcc' => 'victim@example.com',
            ]))
            ->assertOk()
            ->json('data');

        $this->assertTrue($data['sent']);
        $this->assertSame($admin->email, $data['recipient']);
        Mail::assertSent(EmailMailable::class, 1);
        Mail::assertSent(EmailMailable::class, function (EmailMailable $mail) use ($admin) {
            $this->assertCount(1, $mail->to);
            $this->assertTrue($mail->hasTo($admin->email));
            $this->assertEmpty($mail->cc);
            $this->assertEmpty($mail->bcc);
            $this->assertStringStartsWith('[Test] Hello ', $mail->subject);
            $this->assertStringContainsString('Hello ', $mail->getHtml());
            $this->assertStringNotContainsString('@VarUserName', $mail->getHtml());

            return true;
        });
    }

    public function testTestSendDoesNotSendAnInvalidDraft(): void
    {
        Mail::fake();

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/templates/email/test-send', $this->body(['event' => 'App\\Nope']))
            ->assertUnprocessable();

        Mail::assertNothingSent();
    }

    public function testUsersWithoutTheTemplateUpdatePermissionAreRefused(): void
    {
        Mail::fake();

        foreach (['render', 'test-send'] as $endpoint) {
            $this->actingAs($this->makeStudent(), 'api')->postJson("/api/admin/templates/email/{$endpoint}", $this->body())->assertForbidden();
            $this->actingAs($this->makeInstructor(), 'api')->postJson("/api/admin/templates/email/{$endpoint}", $this->body())->assertForbidden();
        }
        Mail::assertNothingSent();
    }

    public function testAnonymousRequestsAreRefused(): void
    {
        foreach (['render', 'test-send'] as $endpoint) {
            $this->postJson("/api/admin/templates/email/{$endpoint}", $this->body())->assertUnauthorized();
        }
    }

    public function testTestSendIsThrottledToFivePerMinute(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($admin, 'api')->postJson('/api/admin/templates/email/test-send', $this->body())->assertOk();
        }
        $this->actingAs($admin, 'api')->postJson('/api/admin/templates/email/test-send', $this->body())->assertStatus(429);
        Mail::assertSent(EmailMailable::class, 5);

        // The render endpoint has its own counter.
        $this->actingAs($admin, 'api')->postJson('/api/admin/templates/email/render', $this->body())->assertOk();
    }

    public function testRenderIsThrottledToThirtyPerMinute(): void
    {
        $admin = $this->makeAdmin();

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($admin, 'api')->postJson('/api/admin/templates/email/render', $this->body())->assertOk();
        }
        $this->actingAs($admin, 'api')->postJson('/api/admin/templates/email/render', $this->body())->assertStatus(429);
    }
}
