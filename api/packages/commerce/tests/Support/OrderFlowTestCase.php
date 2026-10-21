<?php

namespace Ulams\Commerce\Tests\Support;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Commerce\CommerceManager;
use Ulams\Commerce\Models\ProductLink;
use Ulams\Commerce\UlamsCommerceServiceProvider;
use Ulams\CourseAccess\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Courses\Models\User;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\Webhooks\Support\WebhookSignature;

/** Courses, course access, entitlements and commerce with the fake provider as "Sylius" on channel `acme`. */
abstract class OrderFlowTestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;

    public const SECRET = 'whsec_tenant_acme';

    protected FakeCommerceProvider $sylius;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sylius = new FakeCommerceProvider('acme');
        app(CommerceManager::class)->extend('fake', fn () => $this->sylius);
        config([
            'commerce.provider' => 'fake', 'commerce.channel' => 'acme', 'commerce.webhook.secret' => self::SECRET,
            'commerce.webhook.previous_secret' => null, 'queue.default' => 'sync',
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
            UlamsCommerceServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
    }

    protected function course(string $variant = 'variant-course-1'): Course
    {
        $course = Course::factory()->create();
        ProductLink::query()->create([
            'sellable_type' => 'course', 'sellable_id' => $course->getKey(), 'provider' => 'fake', 'external_id' => $variant,
            'amount_minor' => 4900, 'currency' => 'EUR', 'active' => true,
        ]);

        return $course;
    }

    protected function user(): User
    {
        return User::factory()->create();
    }

    /** @param array<string,string> $headers */
    protected function deliver(array $payload, ?string $secret = self::SECRET, ?int $timestamp = null, array $headers = [], ?string $provider = 'fake'): TestResponse
    {
        $body = json_encode($payload);

        return $this->deliverRaw($body, $secret === null ? null : WebhookSignature::sign($secret, $body, $timestamp), $payload['id'] ?? null, $headers, $provider);
    }

    protected function deliverRaw(string $body, ?string $signature, ?string $eventId, array $headers = [], ?string $provider = 'fake'): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($signature !== null) {
            $server['HTTP_ULAMS_SIGNATURE'] = $signature;
        }
        if ($eventId !== null) {
            $server['HTTP_ULAMS_EVENT_ID'] = $eventId;
        }
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', "/api/commerce/webhooks/{$provider}", [], [], [], $server, $body);
    }

    protected function hasAccess(User $user, Course $course): bool
    {
        return CourseUserPivot::query()->where('user_id', $user->getKey())->where('course_id', $course->getKey())->exists();
    }
}
