<?php

namespace EscolaLms\ConsultationAccess\Database\Factories;

use EscolaLms\Auth\Models\User;
use EscolaLms\ConsultationAccess\Models\Consultation;
use EscolaLms\Consultations\Database\Factories\ConsultationFactory as BaseFactory;
use EscolaLms\Core\Enums\UserRole;

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
