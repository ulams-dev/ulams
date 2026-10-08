<?php

namespace EscolaLms\PencilSpaces\Tests\Feature;

use EscolaLms\PencilSpaces\Facades\PencilSpace;
use EscolaLms\PencilSpaces\Models\PencilSpaceAccount;
use EscolaLms\PencilSpaces\Models\User;
use EscolaLms\PencilSpaces\Resource\CreatePencilSpaceResource;
use EscolaLms\PencilSpaces\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class PencilSpaceFacadeTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        PencilSpace::fake();
    }

    public function testGetDirectLoginUrl(): void
    {
        /** @var User $user */
        $user = User::factory()
            ->has(PencilSpaceAccount::factory())
            ->create();

        $this->assertNotNull($user->pencilSpaceAccount);

        $directUrl = PencilSpace::getDirectLoginUrl($user->getKey());
        $this->assertNotNull($directUrl);
    }

    public function testGetDirectLoginUrlWhenUserDoesNotHavePencilSpaceAccount(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->assertNull($user->pencilSpaceAccount);

        $directUrl = PencilSpace::getDirectLoginUrl($user->getKey());
        $this->assertNotNull($directUrl);

        $user->refresh();
        $this->assertNotNull($user->pencilSpaceAccount);
        $this->assertNotNull($user->pencilSpaceAccount->pencil_space_id, );
        $this->assertNotNull($user->pencilSpaceAccount->pencil_space_email);
    }

    public function testCreateSpaceForUsersWithPencilSpaceAccount(): void
    {
        $teacher = User::factory()->has(PencilSpaceAccount::factory())->create();
        $student = User::factory()->has(PencilSpaceAccount::factory())->create();

        $resource = new CreatePencilSpaceResource(
            $this->faker->title,
            collect($teacher->getKey()),
            collect($student->getKey()),
        );

        $space = PencilSpace::createSpace($resource);
        $this->assertNotNull($space);
    }

    public function testCreateSpaceForStudentWithoutPencilSpaceAccount(): void
    {
        /** @var User $student */
        $student = User::factory()->create();
        $teacher = User::factory()->has(PencilSpaceAccount::factory())->create();

        $resource = new CreatePencilSpaceResource(
            $this->faker->title,
            collect($teacher->getKey()),
            collect($student->getKey()),
        );

        $space = PencilSpace::createSpace($resource);
        $this->assertNotNull($space);

        $student->refresh();
        $this->assertNotNull($student->pencilSpaceAccount);
        $this->assertNotNull($student->pencilSpaceAccount->pencil_space_id, );
        $this->assertNotNull($student->pencilSpaceAccount->pencil_space_email);
    }
}
