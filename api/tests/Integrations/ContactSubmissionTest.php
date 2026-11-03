<?php

namespace Tests\Integrations;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Ulams\TemplatesEmail\Core\EmailMailable;

class ContactSubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ulams_tenancy.enforce_known_hosts' => false]);
    }

    public function test_valid_submission_is_sent_to_the_private_contact_inbox(): void
    {
        Mail::fake();

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])->postJson('/api/contact', [
            'name' => 'Alex Example',
            'email' => 'alex@example.com',
            'subject' => 'Self-hosting question',
            'message' => 'Can I run ULAMS on my own server? <script>alert(1)</script>',
            'website' => '',
        ]);
        $response->assertAccepted();

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mail): bool {
            return $mail->hasTo('contact@qunabu.com')
                && $mail->subject === 'ULAMS contact form message'
                && str_contains((string) $mail->getHtml(), 'Can I run ULAMS on my own server?')
                && str_contains((string) $mail->getHtml(), '&lt;script&gt;alert(1)&lt;/script&gt;');
        });
    }

    public function test_invalid_contact_data_is_rejected_without_sending_mail(): void
    {
        Mail::fake();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->postJson('/api/contact', [
            'name' => 'Alex',
            'email' => 'not-an-email',
            'subject' => '',
            'message' => 'Hi',
        ])->assertUnprocessable();

        Mail::assertNothingSent();
    }

    public function test_honeypot_is_accepted_without_sending_mail(): void
    {
        Mail::fake();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->postJson('/api/contact', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'subject' => 'Spam',
            'message' => 'Spam content',
            'website' => 'https://spam.example',
        ])->assertAccepted();

        Mail::assertNothingSent();
    }
}
