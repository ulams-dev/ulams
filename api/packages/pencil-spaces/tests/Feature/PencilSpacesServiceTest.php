<?php

namespace EscolaLms\PencilSpaces\Tests\Feature;

use EscolaLms\PencilSpaces\EscolaLmsPencilSpacesServiceProvider;
use EscolaLms\PencilSpaces\Models\PencilSpaceAccount;
use EscolaLms\PencilSpaces\Models\User;
use EscolaLms\PencilSpaces\Resource\CreatePencilSpaceResource;
use EscolaLms\PencilSpaces\Services\Contracts\PencilSpacesServiceContract;
use EscolaLms\PencilSpaces\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class PencilSpacesServiceTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set(EscolaLmsPencilSpacesServiceProvider::CONFIG_KEY . '.api_key', 'api_key');
        Config::set(EscolaLmsPencilSpacesServiceProvider::CONFIG_KEY . '.api_url', 'http://api-url');
    }

    public function testGetDirectLoginUrl(): void
    {
        /** @var User $user */
        $user = User::factory()
            ->has(PencilSpaceAccount::factory())
            ->create();

        $this->assertNotNull($user->pencilSpaceAccount);
        $url = $this->faker->url;

        Http::fakeSequence()
            ->push(['url' => $url]);

        $directUrl = app(PencilSpacesServiceContract::class)->getDirectLoginUrl($user->getKey());
        $this->assertEquals($url, $directUrl);
    }

    public function testGetDirectLoginUrlWhenUserDoesNotHavePencilSpaceAccount(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->assertNull($user->pencilSpaceAccount);

        $pencilSpaceId = $this->faker->uuid;
        $pencilSpaceEmail = $pencilSpaceId . '@example.com';
        $url = $this->faker->url;

        Http::fakeSequence()
            ->push(['userId' => $pencilSpaceId, 'email' => $pencilSpaceEmail])
            ->push(['url' => $url]);

        $directUrl = app(PencilSpacesServiceContract::class)->getDirectLoginUrl($user->getKey());
        $this->assertEquals($url, $directUrl);

        $user->refresh();
        $this->assertNotNull($user->pencilSpaceAccount);
        $this->assertEquals($pencilSpaceId, $user->pencilSpaceAccount->pencil_space_id, );
        $this->assertEquals($pencilSpaceEmail, $user->pencilSpaceAccount->pencil_space_email);
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

        Http::fakeSequence()
            ->push(['spaceId' => $this->faker->uuid, 'link' => $this->faker->url]);

        $space = app(PencilSpacesServiceContract::class)->createSpace($resource);
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

        $pencilSpaceId = $this->faker->uuid;
        $pencilSpaceEmail = $pencilSpaceId . '@example.com';

        Http::fakeSequence()
            ->push(['userId' => $pencilSpaceId, 'email' => $pencilSpaceEmail])
            ->push(['spaceId' => $this->faker->uuid, 'link' => $this->faker->url]);

        $space = app(PencilSpacesServiceContract::class)->createSpace($resource);
        $this->assertNotNull($space);

        $student->refresh();
        $this->assertNotNull($student->pencilSpaceAccount);
        $this->assertEquals($pencilSpaceId, $student->pencilSpaceAccount->pencil_space_id, );
        $this->assertEquals($pencilSpaceEmail, $student->pencilSpaceAccount->pencil_space_email);
    }
}
