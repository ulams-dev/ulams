<?php

namespace Ulams\Demo\Tests\Api;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\CourseAccess\Models\Course;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Demo\Tests\TestCase;

class DemoApiTest extends TestCase
{
    public function testShowsDemoModeAndAccountsWithoutPasswords(): void
    {
        $this->seedDemoUsers();

        $response = $this->getJson('/api/demo')->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.admin_url', 'http://demotest.admin.localhost')
            ->assertJsonPath('data.users', [
                ['role' => 'student', 'email' => self::STUDENT_EMAIL],
                ['role' => 'admin', 'email' => self::ADMIN_EMAIL],
            ]);

        $this->assertStringNotContainsStringIgnoringCase('password', $response->getContent());
    }

    public function testPublicConfigAnnouncesDemoMode(): void
    {
        $this->getJson('/api/config')->assertOk()
            ->assertJsonPath('data.ulams_demo.enabled', true)
            ->assertJsonPath('data.ulams_demo.admin_url', 'http://demotest.admin.localhost');
    }

    public function testStudentTokenWorksOnProfileWithTheStudentRole(): void
    {
        $this->seedDemoUsers();

        $token = $this->demoLogin('student');

        $this->getJson('/api/profile/me', ['Authorization' => 'Bearer ' . $token])->assertOk()
            ->assertJsonPath('data.email', self::STUDENT_EMAIL)
            ->assertJsonPath('data.roles', ['student']);
    }

    public function testAdminTokenWorksOnProfileWithTheAdminRole(): void
    {
        $this->seedDemoUsers();

        $token = $this->demoLogin('admin');

        $this->getJson('/api/profile/me', ['Authorization' => 'Bearer ' . $token])->assertOk()
            ->assertJsonPath('data.email', self::ADMIN_EMAIL)
            ->assertJsonPath('data.roles', ['admin']);
    }

    public function testLoginHasTheShapeOfTheRegularLogin(): void
    {
        $this->seedDemoUsers();

        $this->postJson('/api/demo/login', ['role' => 'student'])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['token', 'expires_at']]);
    }

    public function testStudentLoginGivesAccessToEveryPublishedCourse(): void
    {
        Event::fake();
        [, $student] = $this->seedDemoUsers();
        $published = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $draft = Course::factory()->create(['status' => CourseStatusEnum::DRAFT]);

        $this->demoLogin('student');
        // idempotent
        $this->demoLogin('student');

        $this->assertSame(1, $published->users()->whereKey($student->getKey())->count());
        $this->assertSame(0, $draft->users()->whereKey($student->getKey())->count());
    }

    public function testTutorLoginIssuesATutorToken(): void
    {
        $this->seedDemoUsers();
        $tutor = \Ulams\Auth\Models\User::factory()->create(['email' => 'tutor@demo-test.ulams.app', 'is_active' => true]);
        $tutor->assignRole('tutor');

        $token = $this->demoLogin('tutor');

        $this->getJson('/api/profile/me', ['Authorization' => 'Bearer ' . $token])->assertOk()
            ->assertJsonPath('data.email', 'tutor@demo-test.ulams.app')
            ->assertJsonPath('data.roles', ['tutor']);
        $this->getJson('/api/demo')->assertJsonFragment(['role' => 'tutor', 'email' => 'tutor@demo-test.ulams.app']);
    }

    public function testTutorLoginWithoutASeededTutorIsRejected(): void
    {
        $this->seedDemoUsers();

        $this->postJson('/api/demo/login', ['role' => 'tutor'])->assertStatus(422);
    }

    public function testRejectsOtherRoles(): void
    {
        $this->seedDemoUsers();

        $this->postJson('/api/demo/login', ['role' => 'teacher'])->assertStatus(422);
        $this->postJson('/api/demo/login', [])->assertStatus(422);
    }

    /**
     * Tenant isolation: every tenant has its own Passport key pair (and database), so a token
     * issued by one tenant is rejected by another. Both "tenants" share this process and test
     * database; switching the key pair is what tells them apart here. The opt-in integration
     * test (tests/Integration) checks the same against real tenant hosts.
     */
    public function testTokenOfOneTenantIsRejectedByAnother(): void
    {
        $this->seedDemoUsers();
        $tokenA = $this->demoLogin('admin');
        $this->getJson('/api/profile/me', ['Authorization' => 'Bearer ' . $tokenA])->assertOk();

        $this->switchToTenantWithOwnKeys();

        $this->getJson('/api/profile/me', ['Authorization' => 'Bearer ' . $tokenA])->assertUnauthorized();
        $tokenB = $this->demoLogin('admin');
        $this->getJson('/api/profile/me', ['Authorization' => 'Bearer ' . $tokenB])->assertOk();
    }

    private function demoLogin(string $role): string
    {
        $token = $this->postJson('/api/demo/login', ['role' => $role])->assertOk()->json('data.token');
        $this->assertIsString($token);
        $this->assertNotSame('', $token);
        // the next request must authenticate from its own header only
        Auth::forgetGuards();

        return $token;
    }

    private function switchToTenantWithOwnKeys(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        $public = openssl_pkey_get_details($key)['key'];

        config(['passport.private_key' => $private, 'passport.public_key' => $public]);
        foreach ([AuthorizationServer::class, ResourceServer::class, AccessTokenRepository::class] as $service) {
            $this->app->forgetInstance($service);
        }
        Auth::forgetGuards();
    }
}
