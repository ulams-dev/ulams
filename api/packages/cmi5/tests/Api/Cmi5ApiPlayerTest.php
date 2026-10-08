<?php

namespace EscolaLms\Cmi5\Tests\Api;

use EscolaLms\Cmi5\Database\Seeders\Cmi5PermissionSeeder;
use EscolaLms\Cmi5\Tests\TestCase;
use EscolaLms\Cmi5\Tests\Traits\Cmi5Testing;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Lrs\Database\Seeders\LrsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class Cmi5ApiPlayerTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, Cmi5Testing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Cmi5PermissionSeeder::class);
        $this->seed(LrsSeeder::class);
    }

    public function testGetPlayer(): void
    {
        $token = $this->getToken();
        $data = $this->uploadCmi5('cmi5.zip', $token);
        $cmi5IdAu = $data->au[0]->id;

        $response = $this
            ->withHeaders([
                'Authorization' => "Bearer {$token}"
            ])
            ->json('GET', '/api/cmi5/player/' . $cmi5IdAu);

        $response->assertOk();
        $response->assertViewIs('cmi5::player');
    }

    public function testGetPlayerNotExistingCmi5Au(): void
    {
        $token = $this->getToken();

        $response = $this
            ->withHeaders([
                'Authorization' => "Bearer {$token}"
            ])
            ->json('GET', '/api/cmi5/player/' . 123);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['au_id']);
    }

    public function testGetPlayerNotExistingCourse(): void
    {
        $token = $this->getToken();
        $data = $this->uploadCmi5('cmi5.zip', $token);
        $cmi5IdAu = $data->au[0]->id;

        $response = $this
            ->withHeaders([
                'Authorization' => "Bearer {$token}"
            ])
            ->json('GET', '/api/cmi5/player/' . $cmi5IdAu . '?course_id=123');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['course_id']);
    }

    public function testGetPlayerNotExistingTopic(): void
    {
        $token = $this->getToken();
        $data = $this->uploadCmi5('cmi5.zip', $token);
        $cmi5IdAu = $data->au[0]->id;

        $response = $this
            ->withHeaders([
                'Authorization' => "Bearer {$token}"
            ])
            ->json('GET', '/api/cmi5/player/' . $cmi5IdAu . '?topic_id=123');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['topic_id']);
    }
}
