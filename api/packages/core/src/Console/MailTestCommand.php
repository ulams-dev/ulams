<?php

namespace Ulams\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Sends one plain test e-mail through the configured mailer, for the setup wizard and operators. */
class MailTestCommand extends Command
{
    protected $signature = 'ulams:mail:test {to : Recipient address}';

    protected $description = 'Send a test e-mail through the configured mailer (MAIL_DRIVER, SMTP settings)';

    public function handle(): int
    {
        $to = (string) $this->argument('to');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error("Not an e-mail address: {$to}");

            return self::FAILURE;
        }
        $mailer = (string) config('mail.default', config('mail.driver'));
        try {
            Mail::raw(
                'This is a test e-mail from ' . config('app.name') . ' (' . config('app.url') . "). If you can read it, outgoing e-mail works.\n",
                fn ($message) => $message->to($to)->subject('[Test] ' . config('app.name') . ' e-mail delivery'),
            );
        } catch (Throwable $e) {
            $this->error("Sending through the {$mailer} mailer failed: " . $e->getMessage());

            return self::FAILURE;
        }
        $this->info($mailer === 'log'
            ? "Written to the log (MAIL_DRIVER=log): no e-mail leaves this installation until SMTP is configured."
            : "Sent to {$to} through the {$mailer} mailer.");

        return self::SUCCESS;
    }
}
