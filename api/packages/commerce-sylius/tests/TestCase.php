<?php

namespace Ulams\CommerceSylius\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\CommerceSylius\UlamsCommerceSyliusServiceProvider;
use Ulams\CourseAccess\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Courses\Models\User;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\Webhooks\Support\WebhookSignature;

/** Courses, entitlements, commerce and the Sylius adapter on channel `acme`; the HTTP calls to Sylius are faked per test. */
abstract class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;

    public const SECRET = 'whsec_tenant_acme_for_tests_only';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'commerce.provider' => 'sylius', 'commerce.channel' => 'acme', 'commerce.webhook.secret' => self::SECRET,
            'commerce.webhook.previous_secret' => null, 'commerce.front_url' => 'https://acme.test', 'queue.default' => 'sync',
            'commerce-sylius.url' => 'http://sylius.test', 'commerce-sylius.admin.email' => 'lms@sylius.test',
            'commerce-sylius.admin.password' => 'not-a-real-password',
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsAuthServiceProvider::class,
            UlamsCourseServiceProvider::class,
            UlamsCommerceSyliusServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }

    protected function user(): User
    {
        return User::factory()->create();
    }

    protected function course(array $attributes = []): Course
    {
        return Course::factory()->create($attributes + ['title' => 'Brewing basics', 'status' => 'published']);
    }

    protected function hasAccess(User $user, Course $course): bool
    {
        return CourseUserPivot::query()->where('user_id', $user->getKey())->where('course_id', $course->getKey())->exists();
    }

    protected function deliver(array $payload, ?string $secret = self::SECRET): TestResponse
    {
        $body = json_encode($payload);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_ULAMS_EVENT_ID' => $payload['id'], 'HTTP_ULAMS_SIGNATURE' => WebhookSignature::sign((string) $secret, $body)];

        return $this->call('POST', '/api/commerce/webhooks/sylius', [], [], [], $server, $body);
    }

    /** @return array<string,mixed> a recorded Sylius response */
    protected function fixture(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__ . "/fixtures/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
    }
}
