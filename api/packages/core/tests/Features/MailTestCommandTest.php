<?php

namespace Ulams\Core\Tests\Features;

use Ulams\Core\Tests\TestCase;

class MailTestCommandTest extends TestCase
{
    public function testSendsOnePlainTestMailToTheGivenAddress(): void
    {
        config(['mail.driver' => 'array', 'mail.default' => 'array', 'app.name' => 'ULAMS']);

        $this->artisan('ulams:mail:test', ['to' => 'admin@example.org'])->expectsOutputToContain('Sent to admin@example.org')->assertSuccessful();

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages[0]->getOriginalMessage();
        $this->assertSame('admin@example.org', $email->getTo()[0]->getAddress());
        $this->assertSame('[Test] ULAMS e-mail delivery', $email->getSubject());
    }

    public function testTheLogMailerSaysNothingLeavesTheInstallation(): void
    {
        config(['mail.driver' => 'log', 'mail.default' => 'log']);

        $this->artisan('ulams:mail:test', ['to' => 'admin@example.org'])->expectsOutputToContain('Written to the log')->assertSuccessful();
    }

    public function testRejectsSomethingThatIsNotAnAddress(): void
    {
        $this->artisan('ulams:mail:test', ['to' => 'nobody'])->expectsOutputToContain('Not an e-mail address')->assertFailed();
    }
}
