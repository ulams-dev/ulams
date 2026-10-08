<?php

namespace Ulams\PencilSpaces\Tests\Api;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\PencilSpaces\Database\Seeders\PencilSpacesPermissionSeeder;
use Ulams\PencilSpaces\Facades\PencilSpace;
use Ulams\PencilSpaces\Models\User;
use Ulams\PencilSpaces\Tests\TestCase;

class PencilSpacesLoginApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PencilSpacesPermissionSeeder::class);
        PencilSpace::fake();
    }

    public function testLoginToPencilSpacesUnauthorized(): void
    {
        $this->postJson('api/pencil-spaces/login')
            ->assertUnauthorized();
    }

    public function testLoginToPencilSpacesForbidden(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson('api/pencil-spaces/login')
            ->assertForbidden();
    }

    public function testLoginToPencilSpaces(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student, 'api')
            ->postJson('api/pencil-spaces/login', [
                'redirect_url' => $this->faker->url,
            ])
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'url',
                ],
            ]);
    }
}
