<?php

namespace Ulams\Consultations\Tests\APIs;

use Ulams\Auth\Dtos\Admin\UserAssignableDto;
use Ulams\Auth\Services\Contracts\UserServiceContract;
use Ulams\Categories\Models\Category;
use Ulams\Consultations\Database\Seeders\ConsultationsPermissionSeeder;
use Ulams\Consultations\Enum\ConsultationsPermissionsEnum;
use Ulams\Consultations\Enum\ConsultationStatusEnum;
use Ulams\Consultations\Models\Consultation;
use Ulams\Consultations\Models\ConsultationUserPivot;
use Ulams\Consultations\Tests\Models\User;
use Ulams\Consultations\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;

class ConsultationApiTest extends TestCase
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
        $this->consultation = Consultation::factory([
            'status' => ConsultationStatusEnum::PUBLISHED
        ])->create();
        $this->consultation->author()->associate($this->user);
        $this->categories = Category::factory(2)->create();
        $this->consultation->categories()->sync($this->categories->pluck('id')->toArray());
    }

    public function testConsultationsList(): void
    {
        $this->response = $this->actingAs($this->user, 'api')->get('/api/admin/consultations');
        $this->response->assertOk();
    }

    public function testConsultationListAPI()
    {
        $response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/consultations/'
        );
        $response->assertOk();
    }

    public function testConsultationListAdminFilterOnlyWithCategories(): void
    {
        $consultation = Consultation::factory()->create();
        $filterData = [
            'only_with_categories=true'
        ];
        $response = $this->actingAs($this->user, 'api')->get('/api/admin/consultations?' . implode('&', $filterData));

        $response->assertOk();
        $responseConsultationId = collect(json_decode($response->content(), true)['data'])->pluck('id')->toArray();

        $this->assertTrue(!in_array($consultation->getKey(), $responseConsultationId));
        $response = $this->actingAs($this->user, 'api')->get('/api/admin/consultations');

        $response->assertOk();
        $responseConsultationId = collect(json_decode($response->content(), true)['data'])->pluck('id')->toArray();
        $this->assertTrue(in_array($consultation->getKey(), $responseConsultationId));
    }

    public function testConsultationListAPIFilterOnlyWithCategories(): void
    {
        $consultation = Consultation::factory([
            'status' => ConsultationStatusEnum::PUBLISHED
        ])->create();
        $filterData = [
            'only_with_categories=true'
        ];
        $response = $this->get('/api/consultations?' . implode('&', $filterData));

        $response->assertOk();
        $responseConsultationId = collect(json_decode($response->content(), true)['data'])->pluck('id')->toArray();

        $this->assertTrue(!in_array($consultation->getKey(), $responseConsultationId));
        $response = $this->get('/api/consultations');
        $response->assertOk();
        $responseConsultationId = collect(json_decode($response->content(), true)['data'])->pluck('id')->toArray();
        $this->assertTrue(in_array($consultation->getKey(), $responseConsultationId));
    }

    public function testConsultationsListByDate(): void
    {
        $now = now();
        $cons = Consultation::factory([
            'active_from' => $now->modify('+1 days'),
            'active_to' => (clone $now)->modify('+1 days')
        ])->create();
        $this->response = $this->actingAs($this->user, 'api')->get('/api/consultations');

        $this->response->assertOk();
        $this->response->assertJsonMissing([$cons->toArray()]);
    }

    public function testConsultationsListWithFilter(): void
    {
        $categories = $this->categories->pluck('id')->toArray();
        $filterData = [
            'name=' . $this->consultation->name,
            'status[]=' . $this->consultation->status,
            'categories[]=' . $categories[0]
        ];
        $this->response = $this->actingAs($this->user, 'api')->get('/api/admin/consultations?' . implode('&', $filterData));

        $this->response->assertOk();
        $this->response->assertJsonFragment([
            'id' => $this->consultation->getKey(),
            'name' => $this->consultation->name,
            'status' => $this->consultation->status,
            'created_at' => $this->consultation->created_at,
            'categories' => $this->consultation->categories->toArray()
        ]);
        $this->response->assertJson(fn(AssertableJson $json) => $json->has('data', fn(AssertableJson $json) => $json->each(fn(AssertableJson $json) => $json->has('author')->etc())->etc()
        )->etc()
        );
    }

    public function testConsultationsListUnauthorized(): void
    {
        $this->response = $this->json('GET', '/api/admin/consultations');

        $this->response->assertUnauthorized();
    }

    public function testConsultationsAssignableUsersUnauthorized(): void
    {
        $this->response = $this
            ->json('GET', '/api/admin/consultations/users/assignable')
            ->assertUnauthorized();
    }

    public function testConsultationsAssignableUsers(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        $dto = UserAssignableDto::instantiateFromArray(['assignable_by' => ConsultationsPermissionsEnum::CONSULTATION_CREATE]);
        $users = app(UserServiceContract::class)->assignableUsersWithCriteria($dto);
        assert($users instanceof LengthAwarePaginator);

        $this->response = $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/consultations/users/assignable')
            ->assertOk()
            ->assertJsonCount(min($users->total(), $users->perPage()), 'data')
            ->assertJsonMissing([
                'id' => $student->getKey(),
                'email' => $student->email,
            ])
            ->assertJsonFragment([
                'id' => $admin->getKey(),
                'email' => $admin->email,
            ])
            ->assertJsonFragment([
                'id' => $this->user->getKey(),
                'email' => $this->user->email,
            ]);
    }

    public function testConsultationsAssignableUsersSearch(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        $dto = UserAssignableDto::instantiateFromArray([
            'assignable_by' => ConsultationsPermissionsEnum::CONSULTATION_CREATE,
            'search' => $admin->email,
        ]);
        $users = app(UserServiceContract::class)->assignableUsersWithCriteria($dto);
        assert($users instanceof LengthAwarePaginator);

        $this->response = $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/consultations/users/assignable', ['search' => $admin->email])
            ->assertOk()
            ->assertJsonCount(min($users->total(), $users->perPage()), 'data')
            ->assertJsonFragment([
                'id' => $admin->getKey(),
                'email' => $admin->email,
            ])
            ->assertJsonMissing([
                'id' => $student->getKey(),
                'email' => $student->email,
            ])
            ->assertJsonMissing([
                'id' => $this->user->getKey(),
                'email' => $this->user->email,
            ]);
    }

    /**
     * The webcam frame capture endpoints were removed (ADR 0006); they accepted
     * unauthenticated uploads and must stay gone.
     */
    public function testWebcamFrameEndpointsAreRemoved(): void
    {
        Storage::fake();

        foreach (['/api/consultations/save-screen', '/api/consultations/signed-screen-urls'] as $uri) {
            $status = $this->json('POST', $uri, [
                'consultation_id' => 1,
                'user_id' => 1,
                'user_termin_id' => 1,
                'executed_at' => now()->format('Y-m-d H:i:s'),
                'files' => [
                    [
                        'file' => UploadedFile::fake()->image('image.jpg'),
                        'filename' => 'frame.webp',
                        'timestamp' => now()->format('Y-m-d H:i:s'),
                    ],
                ],
            ])->getStatusCode();

            $this->assertContains($status, [404, 405], "{$uri} must not be routable");
        }

        $this->assertEmpty(Storage::allFiles());
    }
}
