<?php

namespace Ulams\ConsultationAccess\Database\Factories;

use Ulams\Auth\Models\User;
use Ulams\ConsultationAccess\Models\Consultation;
use Ulams\Consultations\Database\Factories\ConsultationFactory as BaseFactory;
use Ulams\Core\Enums\UserRole;

class ConsultationFactory extends BaseFactory
{
    protected $model = Consultation::class;

    public function definition(): array
    {
        $author = User::factory()->create();
        $author->assignRole(UserRole::ADMIN);

        return array_merge(parent::definition(), [
            'author_id' => $author->getKey(),
        ]);
    }
}
