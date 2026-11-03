<?php

namespace Ulams\CourseVariants\Tests\Feature;

use Ulams\Core\Tests\Concerns\ForeignTenantToken;
use Ulams\CourseVariants\Tests\TestCase;

/**
 * Tenants have separate databases and their own Passport key pair (ADR 0007), so a token issued by one tenant is rejected by
 * another on every variant endpoint that needs a sign-in; ids of another tenant never resolve (ADR 0190, ADR 0192).
 */
class TenantIsolationTest extends TestCase
{
    use ForeignTenantToken;

    public function testATokenOfAnotherTenantIsRejectedEverywhere(): void
    {
        $family = $this->family();
        $token = $this->admin()->createToken('test')->accessToken;
        $course = $family['default']->getKey();
        $group = $family['group']->id;
        $endpoints = [
            ['GET', "/api/courses/{$course}/variant", []],
            ['PUT', "/api/courses/{$course}/variant", ['course_id' => $course]],
            ['POST', "/api/courses/{$course}/variant/hints/1/dismiss", []],
            ['GET', "/api/courses/{$course}/placement", []],
            ['POST', "/api/courses/{$course}/placement/attempts", ['answers' => []]],
            ['GET', '/api/admin/course-variant-groups', []],
            ['GET', "/api/admin/course-variant-groups/{$group}", []],
            ['PUT', "/api/admin/course-variant-groups/{$group}", ['lock_assigned' => true]],
            ['GET', "/api/admin/course-variant-groups/{$group}/assignments", []],
            ['PUT', "/api/admin/course-variant-groups/{$group}/assignments", ['course_id' => $course, 'user_ids' => [1]]],
            ['GET', "/api/admin/course-variant-groups/{$group}/rules", []],
            ['POST', "/api/admin/course-variant-groups/{$group}/rules", []],
            ['PUT', "/api/admin/course-variant-groups/{$group}/rules/1", []],
            ['DELETE', "/api/admin/course-variant-groups/{$group}/rules/1", []],
            ['POST', "/api/admin/course-variant-groups/{$group}/rules/1/evaluate", []],
            ['GET', "/api/admin/course-variant-groups/{$group}/placement", []],
            ['POST', "/api/admin/course-variant-groups/{$group}/placement/rebuild", []],
        ];

        $this->sendAs($token, 'GET', "/api/admin/course-variant-groups/{$group}")->assertOk();

        $this->switchToTenantWithOwnKeys();

        foreach ($endpoints as [$method, $url, $body]) {
            $this->sendAs($token, $method, $url, $body)->assertUnauthorized();
            $this->sendAs(null, $method, $url, $body)->assertUnauthorized();
        }
    }

    public function testThePublicPickerNeedsNoTokenAndLeaksNothingButLabels(): void
    {
        $family = $this->family();

        $body = $this->getJson("/api/courses/{$family['default']->getKey()}/variants")->assertOk()->json('data');

        $this->assertEqualsCanonicalizing(['groupId', 'defaultCourseId', 'variants'], array_keys($body));
        foreach ($body['variants'] as $variant) {
            $this->assertEqualsCanonicalizing(['courseId', 'title', 'label', 'level', 'role', 'isDefault', 'description', 'duration'], array_keys($variant));
        }
    }
}
