<?php

namespace Ulams\Compliance\Tests;

use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use Ulams\Notifications\UlamsNotificationsServiceProvider;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\TemplatesEmail\Core\EmailMailable;
use Ulams\TemplatesEmail\Database\Seeders\TemplatesEmailSeeder;
use Ulams\TemplatesEmail\Services\Contracts\MjmlServiceContract;
use Ulams\TemplatesEmail\Services\MjmlService;
use Ulams\TemplatesEmail\UlamsTemplatesEmailServiceProvider;
use Ulams\Notifications\Models\DatabaseNotification;

/**
 * The compliance suite with the real notification stack on: e-mail templates (templates-email, MJML mocked), the
 * in-app notifications table and a faked mailer. Tests read what was sent with `sentMails()` and `inApp()`.
 */
abstract class NotificationsTestCase extends TestCase
{
    /** Languages the default templates are written in; an empty list is one template without a language. */
    protected array $templateLocales = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->instance(
            MjmlServiceContract::class,
            Mockery::mock(MjmlService::class, function (MockInterface $mock) {
                $mock->shouldReceive('render')->andReturnArg(0);
                $mock->shouldReceive('renderWithErrors')->andReturnUsing(fn (string $mjml) => ['html' => $mjml, 'errors' => []]);
            })
        );
        config([UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.seed_locales' => $this->templateLocales]);
        $this->seed(TemplatesEmailSeeder::class);
        Mail::fake();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            UlamsSettingsServiceProvider::class,
            UlamsTemplatesEmailServiceProvider::class,
            UlamsNotificationsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ulams_settings.use_database', true);
        $app['config']->set(UlamsTemplatesEmailServiceProvider::CONFIG_KEY . '.mjml.use_api', true);
        $app['config']->set('ulams.core.security.trusted_origins', ['https://ulams.app']);
        $app['config']->set('app.frontend_url', 'https://academy.example.test');
    }

    /**
     * The compliance e-mails sent so far, oldest first, as `[recipient, subject]`.
     *
     * @return list<array{0: string, 1: string}>
     */
    protected function sentMails(): array
    {
        return Mail::sent(EmailMailable::class)
            ->map(fn (EmailMailable $mail) => [$mail->to[0]['address'], (string) $mail->subject])
            ->values()
            ->all();
    }

    /** Body of the e-mail sent to `$to` with this subject. */
    protected function mailBody(string $to, string $subject): string
    {
        $mail = Mail::sent(EmailMailable::class)->first(fn (EmailMailable $m) => $m->to[0]['address'] === $to && $m->subject === $subject);
        $this->assertNotNull($mail, "No mail '{$subject}' to {$to}");

        // `html` is protected on the mailable; this reads what the recipient gets
        return (string) (fn () => $this->html)->call($mail);
    }

    /**
     * How many in-app notifications of each compliance event one user has, by short event name (sorted).
     *
     * @return array<string, int>
     */
    protected function inApp(int $userId): array
    {
        $counts = array_count_values(
            DatabaseNotification::query()
                ->where('notifiable_id', $userId)
                ->pluck('event')
                ->filter(fn ($event) => str_starts_with((string) $event, 'Ulams\\Compliance\\Events\\'))
                ->map(fn (string $event) => class_basename($event))
                ->all()
        );
        ksort($counts);

        return $counts;
    }
}
