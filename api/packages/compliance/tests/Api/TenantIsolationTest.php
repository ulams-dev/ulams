<?php

namespace Ulams\Compliance\Tests\Api;

use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Ulams\Compliance\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Models\Course;

/**
 * Tenants have separate databases and their own Passport key pair (ADR 0007), so a token issued by
 * one tenant must be rejected by another on every compliance endpoint. Same technique as
 * packages/adapt/tests/Api/TenantIsolationTest.php.
 */
class TenantIsolationTest extends TestCase
{
    use CreatesUsers;

    public function testATokenOfAnotherTenantIsRejectedEverywhere(): void
    {
        $token = $this->makeAdmin()->createToken('test')->accessToken;
        $course = Course::factory()->create();
        $endpoints = [
            ['GET', "/api/admin/compliance/courses/{$course->getKey()}/settings", []],
            ['PUT', "/api/admin/compliance/courses/{$course->getKey()}/settings", ['mandatory' => true]],
            ['GET', '/api/admin/compliance/assignments', []],
            ['POST', '/api/admin/compliance/assignments', ['course_id' => $course->getKey(), 'user_ids' => [1]]],
            ['DELETE', '/api/admin/compliance/assignments/1', []],
            ['GET', '/api/admin/compliance/managers', []],
            ['PUT', '/api/admin/compliance/managers/1', ['manager_id' => null]],
            ['GET', '/api/admin/compliance/paths', []],
            ['POST', '/api/admin/compliance/paths', ['title' => 'x']],
            ['GET', '/api/admin/compliance/paths/1', []],
            ['PUT', '/api/admin/compliance/paths/1', ['title' => 'x']],
            ['DELETE', '/api/admin/compliance/paths/1', []],
            ['POST', '/api/admin/compliance/paths/1/enrollments', ['user_ids' => [1]]],
            ['GET', '/api/admin/compliance/rules', []],
            ['POST', '/api/admin/compliance/rules', []],
            ['PUT', '/api/admin/compliance/rules/1', []],
            ['DELETE', '/api/admin/compliance/rules/1', []],
            ['POST', '/api/admin/compliance/rules/1/evaluate', []],
            ['GET', '/api/compliance/my-paths', []],
            ['GET', '/api/admin/compliance/report?dataset=assignments', []],
            ['GET', '/api/admin/compliance/report/export?dataset=certificates', []],
            ['GET', '/api/compliance/my-assignments', []],
            ['GET', '/api/compliance/team/assignments', []],
        ];

        $this->call_($token, 'GET', '/api/admin/compliance/assignments')->assertOk();

        $this->switchToTenantWithOwnKeys();

        foreach ($endpoints as [$method, $url, $body]) {
            $this->call_($token, $method, $url, $body)->assertUnauthorized();
        }
    }

    private function call_(string $token, string $method, string $url, array $body = [])
    {
        $response = $this->json($method, $url, $body, ['Authorization' => 'Bearer ' . $token]);
        Auth::forgetGuards();

        return $response;
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
