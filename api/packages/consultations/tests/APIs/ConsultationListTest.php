<?php

namespace EscolaLms\Consultations\Tests\APIs;

use EscolaLms\Categories\Models\Category;
use EscolaLms\Consultations\Database\Seeders\ConsultationsPermissionSeeder;
use EscolaLms\Consultations\Enum\ConsultationStatusEnum;
use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Tests\Models\User;
use EscolaLms\Consultations\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Testing\Fluent\AssertableJson;

class ConsultationListTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private Consultation $consultation;

    private Collection $categories;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConsultationsPermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('tutor');
    }

    public function testConsultationListForbidden(): void
    {
        $this
            ->actingAs($this->makeStudent(), 'api')
            ->getJson('/api/admin/consultations')
            ->assertForbidden();
    }

    public function testConsultationListUnauthorized(): void
    {
        $this
            ->getJson('/api/admin/consultations')
            ->assertUnauthorized();
    }

    public function testConsultationListWithSorts(): void
    {
        $category = Category::factory()->create();

        /** @var Consultation $consultationOne */
        $consultationOne = Consultation::factory()->create([
            'name' => 'A One',
            'status' => ConsultationStatusEnum::PUBLISHED,
            'duration' => 10,
            'active_from' => now()->subDays(5),
            'active_to' => now()->addDays(2),
            'created_at' => now()->subDays(1),
        ]);

        $consultationOne->categories()->save($category);

        /** @var Consultation $consultationTwo */
        $consultationTwo = Consultation::factory()->create([
            'name' => 'B Two',
            'status' => ConsultationStatusEnum::DRAFT,
            'duration' => 20,
            'active_from' => now()->subDays(2),
            'active_to' => now()->addDays(5),
            'created_at' => now(),
        ]);

        $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/consultations', [
                'date_from' => now()->subDays(3)->format('Y-m-d')
            ])
            ->assertJsonCount(1, 'data')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'B Two',
            ]);

        $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/consultations', [
                'date_to' => now()->addDays(3)->format('Y-m-d')
            ])
            ->assertJsonCount(1, 'data')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'A One',
            ]);

        $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/consultations', [
                'name' => 'One'
            ])
            ->assertJsonCount(1, 'data')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'A One',
            ]);

        $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/consultations', [
                'categories' => [
                    $category->getKey(),
                ],
            ])
            ->assertJsonCount(1, 'data')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'A One',
            ]);

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/consultations', [
            'order_by' => 'created_at',
            'order' => 'ASC'
        ]);

        $response->assertOk();

        $this->assertTrue($response->json('data.0.id') === $consultationOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $consultationTwo->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/consultations', [
            'order_by' => 'created_at',
            'order' => 'DESC'
        ]);

        $response->assertOk();

        $this->assertTrue($response->json('data.0.id') === $consultationTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $consultationOne->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/consultations', [
            'order_by' => 'id',
            'order' => 'ASC'
        ]);

        $response->assertOk();

        $this->assertTrue($response->json('data.0.id') === $consultationOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $consultationTwo->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/consultations', [
            'order_by' => 'id',
            'order' => 'DESC'
        ]);

        $response->assertOk();

        $this->assertTrue($response->json('data.0.id') === $consultationTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $consultationOne->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/consultations', [
            'order_by' => 'name',
            'order' => 'ASC'
        ]);

        $response->assertOk();

        $this->assertTrue($response->json('data.0.id') === $consultationOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $consultationTwo->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/consultations', [
            'order_by' => 'name',
            'order' => 'DESC'
        ]);
        $response->assertOk();

        $this->assertTrue($response->json('data.0.id') === $consultationTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $consultationOne->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/consultations', [
            'order_by' => 'status',
            'order' => 'DESC'
        ]);

        $response->assertOk();

        $this->assertTrue($response->json('data.0.id') === $consultationOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $consultationTwo->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/consultations', [
            'order_by' => 'status',
            'order' => 'ASC'
        ]);
        $response->assertOk();

        $this->assertTrue($response->json('data.0.id') === $consultationTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $consultationOne->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/consultations', [
            'order_by' => 'duration',
            'order' => 'ASC'
        ]);

        $response->assertOk();

        $this->assertTrue($response->json('data.0.id') === $consultationOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $consultationTwo->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/consultations', [
            'order_by' => 'duration',
            'order' => 'DESC'
        ]);

        $response->assertOk();

        $this->assertTrue($response->json('data.0.id') === $consultationTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $consultationOne->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/consultations', [
            'order_by' => 'active_from',
            'order' => 'ASC'
        ]);

        $response->assertOk();

        $this->assertTrue($response->json('data.0.id') === $consultationOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $consultationTwo->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/consultations', [
            'order_by' => 'active_from',
            'order' => 'DESC'
        ]);

        $response->assertOk();

        $this->assertTrue($response->json('data.0.id') === $consultationTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $consultationOne->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/consultations', [
            'order_by' => 'active_to',
            'order' => 'ASC'
        ]);

        $response->assertOk();

        $this->assertTrue($response->json('data.0.id') === $consultationOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $consultationTwo->getKey());

        $response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/consultations', [
            'order_by' => 'active_to',
            'order' => 'DESC'
        ]);

        $response->assertOk();

        $this->assertTrue($response->json('data.0.id') === $consultationTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $consultationOne->getKey());
    }

    public function testConsultationListOwn(): void
    {
        $author1 = $this->makeAdmin();
        $author2 = $this->makeInstructor();
        Consultation::factory()->count(3)->state(['author_id' => $author1->getKey()])->create();
        Consultation::factory()->count(5)
            ->sequence(
                ['author_id' => $author2->getKey()],
                ['author_id' => $author2->getKey()],
                ['author_id' => $author2->getKey()],
                ['author_id' => $author2->getKey()],
                ['author_id' => $author2->getKey()],
            )
            ->create();

        $this
            ->actingAs($author1, 'api')
            ->getJson('/api/admin/consultations')
            ->assertJsonCount(8, 'data');

        $this
            ->actingAs($author2, 'api')
            ->getJson('/api/admin/consultations')
            ->assertJsonCount(5, 'data');
    }
}
