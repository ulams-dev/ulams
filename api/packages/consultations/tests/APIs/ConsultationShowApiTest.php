<?php

namespace EscolaLms\Consultations\Tests\APIs;

use EscolaLms\Consultations\Database\Seeders\ConsultationsPermissionSeeder;
use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\ModelFields\Facades\ModelFields;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\Fluent\AssertableJson;

class ConsultationShowApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConsultationsPermissionSeeder::class);
    }

    public function testConsultationShowUnauthorized(): void
    {
        $this
            ->getJson('/api/admin/consultations/123')
            ->assertUnauthorized();
    }

    public function testConsultationShowForbidden(): void
    {
        $this
            ->actingAs($this->makeStudent(), 'api')
            ->getJson('/api/admin/consultations/' . Consultation::factory()->create()->getKey())
            ->assertForbidden();
    }

    public function testConsultationShowAuthored(): void
    {
        $author1 = $this->makeInstructor();
        $author2 = $this->makeInstructor();
        $consultation1 = Consultation::factory()->state(['author_id' => $author1->getKey()])->create();
        $consultation2 = Consultation::factory()->state(['author_id' => $author2->getKey()])->create();

        $this
            ->actingAs($author1, 'api')
            ->getJson('/api/admin/consultations/' . $consultation1->getKey())
            ->assertOk()
            ->assertJsonFragment([
                'id' => $consultation1->getKey(),
                'name' => $consultation1->name,
                'status' => $consultation1->status,
                'created_at' => $consultation1->created_at,
            ])
            ->assertJson(
                fn(AssertableJson $json) => $json
                    ->has('data', fn(AssertableJson $json) => $json->has('author')->etc())->etc()
            )
            ->assertJsonFragment(['success' => true]);

        $this
            ->actingAs($author1, 'api')
            ->getJson('/api/admin/consultations/' . $consultation2->getKey())
            ->assertForbidden();

        $this
            ->actingAs($author2, 'api')
            ->getJson('/api/admin/consultations/' . $consultation2->getKey())
            ->assertOk();
    }

    public function testConsultationShowAdmin(): void
    {
        $author = $this->makeInstructor();
        $consultation = Consultation::factory()->state(['author_id' => $author->getKey()])->create();

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->getJson('/api/admin/consultations/' . $consultation->getKey())
            ->assertOk()
            ->assertJsonFragment([
                'id' => $consultation->getKey(),
                'name' => $consultation->name,
                'status' => $consultation->status,
                'created_at' => $consultation->created_at,
            ])
            ->assertJson(
                fn(AssertableJson $json) => $json
                    ->has('data', fn(AssertableJson $json) => $json->has('author')->etc())->etc()
            )
            ->assertJsonFragment(['success' => true]);
    }

    public function testConsultationShowNotFound(): void
    {
        $this
            ->actingAs($this->makeInstructor(), 'api')
            ->getJson('/api/admin/consultations/123')
            ->assertStatus(422);
    }

    public function testConsultationShowAPI(): void
    {
        $this
            ->actingAs($this->makeStudent(), 'api')
            ->getJson('/api/consultations/' . Consultation::factory()->create()->getKey())
            ->assertOk();
    }

    public function testConsultationShowModelFields(): void
    {
        ModelFields::addOrUpdateMetadataField(
            Consultation::class,
            'extra_field',
            'text',
            '',
            ['required', 'string', 'max:255']
        );

        $author = $this->makeInstructor();
        $consultation = Consultation::factory()->state(['author_id' => $author->getKey()])->create(['extra_field' => 'value']);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->getJson('/api/admin/consultations/' . $consultation->getKey())
            ->assertOk()
            ->assertJsonFragment([
                'id' => $consultation->getKey(),
                'name' => $consultation->name,
                'status' => $consultation->status,
                'created_at' => $consultation->created_at,
                'extra_field' => 'value',
            ]);
    }
}
