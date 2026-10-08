<?php

namespace EscolaLms\Consultations\Tests\APIs;

use EscolaLms\Categories\Models\Category;
use EscolaLms\Consultations\Database\Seeders\ConsultationsPermissionSeeder;
use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\ModelFields\Facades\ModelFields;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\Fluent\AssertableJson;
use EscolaLms\Consultations\Tests\Models\User;

class ConsultationStoreApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private string $apiUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConsultationsPermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('tutor');
        $this->apiUrl = '/api/admin/consultations';
    }

    public function testConsultationStoreUnauthorized(): void
    {
        $this
            ->postJson($this->apiUrl)
            ->assertUnauthorized();
    }

    public function testConsultationStoreForbidden(): void
    {
        $this
            ->actingAs($this->makeStudent(), 'api')
            ->postJson($this->apiUrl)
            ->assertForbidden();
    }

    public function testConsultationStore(): void
    {
        $consultation = Consultation::factory()->make();
        $consultationArr = $consultation->toArray();
        $this->assertTrue(!isset($consultation['image_path']));
        $this->assertTrue(!isset($consultation['logotype_path']));
        $proposedTerms = [
            now()->format("Y-m-d\TH:i:s.000000\Z"),
            now()->modify('+1 day')->format("Y-m-d\TH:i:s.000000\Z")
        ];
        $categories = Category::factory(2)->create()->pluck('id')->toArray();
        $teachers = User::factory()->count(4)->create();
        $requestArray = array_merge(
            $consultationArr,
            ['proposed_terms' => $proposedTerms],
            ['image' => UploadedFile::fake()->image('image.jpg')],
            ['logotype' => UploadedFile::fake()->image('image.jpg')],
            ['categories' => $categories],
            ['max_session_students' => 5],
            ['teachers' => $teachers->pluck('id')->toArray()],
        );
        $response = $this->actingAs($this->user, 'api')->json(
            'POST',
            $this->apiUrl,
            $requestArray
        );
        $response->assertCreated();
        $response->assertJsonFragment([
            'name' => $consultationArr['name'],
            'short_desc' => $consultationArr['short_desc'],
            'status' => $consultationArr['status'],
            'max_session_students' => 5,
        ]);
        $response->assertJsonFragment([
            'proposed_terms' => $proposedTerms
        ]);
        $response->assertJsonFragment(['success' => true]);
        $response->assertJson(fn (AssertableJson $json) => $json->has(
            'data',
            fn ($json) => $json
                ->has('image_path')
                ->has('logotype_path')
                ->has('categories', fn (AssertableJson $json) =>
                    $json->each(fn (AssertableJson $json) =>
                        $json->where('id', fn ($json) =>
                            in_array($json, $categories)
                        )
                        ->etc()
                    )
                    ->etc()
                )
                ->has('author')
                ->etc()
            )
            ->etc()
        );
        $response->assertJsonCount(4, 'data.teachers');
    }

    public function testConsultationStoreWithModelFields(): void
    {
        ModelFields::addOrUpdateMetadataField(
            Consultation::class,
            'extra_field',
            'text',
            '',
            ['required', 'string', 'max:255']
        );

        $consultation = Consultation::factory()->make();
        $consultationArr = $consultation->toArray();
        $requestArray = array_merge(
            $consultationArr,
            [
                'extra_field' => 'value',
            ],
        );
        $this->actingAs($this->user, 'api')->json(
            'POST',
            $this->apiUrl,
            $requestArray
        )
            ->assertCreated()
            ->assertJsonFragment([
                'name' => $consultationArr['name'],
                'short_desc' => $consultationArr['short_desc'],
                'status' => $consultationArr['status'],
                'extra_field' => 'value',
            ]);
    }

    public function testConsultationStoreRequiredValidation(): void
    {
        $response = $this->actingAs($this->user, 'api')->json('POST', $this->apiUrl);
        $response->assertJsonValidationErrors(['name', 'status', 'description']);
    }
}
