<?php

namespace Ulams\Consultations\Tests\APIs;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ulams\Consultations\Database\Seeders\ConsultationsPermissionSeeder;
use Ulams\Consultations\Enum\ConsultationStatusEnum;
use Ulams\Consultations\Models\Consultation;
use Ulams\Consultations\Tests\Models\User;
use Ulams\Consultations\Tests\TestCase;

/**
 * Anonymous visitors see the public profile of a consultation's author and teachers, never
 * their contact details or address.
 */
class ConsultationPublicAuthorTest extends TestCase
{
    use DatabaseTransactions;

    private const PRIVATE_FIELDS = ['email', 'phone', 'street', 'postcode', 'city', 'country', 'gender', 'age', 'email_verified_at', 'is_active', 'password', 'remember_token'];

    private User $tutor;
    private Consultation $consultation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConsultationsPermissionSeeder::class);

        $this->tutor = User::factory()->create([
            'email' => 'private-tutor@example.com',
            'phone' => '+48 600 000 000',
            'street' => 'Secret 1',
            'city' => 'Kraków',
            'postcode' => '30-001',
        ]);
        $this->tutor->guard_name = 'api';
        $this->tutor->assignRole('tutor');
        $this->consultation = Consultation::factory(['status' => ConsultationStatusEnum::PUBLISHED])->create();
        $this->consultation->author()->associate($this->tutor)->save();
        $this->consultation->teachers()->sync([$this->tutor->getKey()]);
    }

    public function testPublicListShowsOnlyThePublicProfile(): void
    {
        $response = $this->getJson('/api/consultations?per_page=100')->assertOk();
        $item = collect($response->json('data'))->firstWhere('id', $this->consultation->getKey());

        $this->assertNotNull($item);
        $this->assertPublicProfile($item['author']);
        $this->assertPublicProfile($item['teachers'][0]);
        $this->assertStringNotContainsString('private-tutor@example.com', $response->getContent());
        $this->assertStringNotContainsString('Secret 1', $response->getContent());
    }

    public function testPublicShowShowsOnlyThePublicProfile(): void
    {
        $response = $this->getJson('/api/consultations/' . $this->consultation->getKey())->assertOk();

        $this->assertPublicProfile($response->json('data.author'));
        $this->assertStringNotContainsString('private-tutor@example.com', $response->getContent());
    }

    private function assertPublicProfile(array $author): void
    {
        $this->assertSame($this->tutor->getKey(), $author['id']);
        $this->assertSame($this->tutor->first_name, $author['first_name']);
        $this->assertSame($this->tutor->last_name, $author['last_name']);
        $this->assertArrayHasKey('path_avatar', $author);
        foreach (self::PRIVATE_FIELDS as $field) {
            $this->assertArrayNotHasKey($field, $author, $field);
        }
    }
}
