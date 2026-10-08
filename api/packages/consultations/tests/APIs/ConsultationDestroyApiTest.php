<?php

namespace EscolaLms\Consultations\Tests\APIs;

use EscolaLms\Consultations\Database\Seeders\ConsultationsPermissionSeeder;
use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use EscolaLms\Consultations\Tests\Models\User;

class ConsultationDestroyApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private Consultation $consultation;

    private string $apiUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConsultationsPermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('tutor');
    }

    private function initVariable(): void
    {
        $this->consultation = Consultation::factory()->create();
        $this->apiUrl = '/api/admin/consultations/' . $this->consultation->getKey();
    }

    public function testConsultationDestroyUnauthorized(): void
    {
        $this->initVariable();

        $this
            ->deleteJson($this->apiUrl)
            ->assertUnauthorized();
    }

    public function testConsultationDestroyForbidden(): void
    {
        $this->initVariable();

        $this
            ->actingAs($this->makeStudent(), 'api')
            ->deleteJson($this->apiUrl)
            ->assertForbidden();
    }

    public function testConsultationDestroyAuthored(): void
    {
        $this->initVariable();

        $author1 = $this->makeInstructor();
        $author2 = $this->makeInstructor();
        $consultation = Consultation::factory()->state(['author_id' => $author1->getKey()])->create();

        $this
            ->actingAs($author2, 'api')
            ->deleteJson('/api/admin/consultations/' . $consultation->getKey())
            ->assertForbidden();

        $this
            ->actingAs($author1, 'api')
            ->deleteJson('/api/admin/consultations/' . $consultation->getKey())
            ->assertOk();
    }

    public function testConsultationDestroy(): void
    {
        $this->initVariable();

        $this
            ->actingAs($this->user, 'api')->deleteJson($this->apiUrl)
            ->assertOk()
            ->assertJsonFragment(['success' => true]);
    }

    public function testConsultationDestroyFailed(): void
    {
        $this
            ->actingAs($this->user, 'api')
            ->getJson('/api/admin/consultations/123')
            ->assertUnprocessable();
    }
}
