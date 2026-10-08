<?php

namespace EscolaLms\Consultations\Database\Seeders;

use EscolaLms\Consultations\Enum\ConsultationTermStatusEnum;
use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
use EscolaLms\Core\Models\User;
use Illuminate\Database\Seeder;

class ConsultationTermsSeeder extends Seeder
{
    private ?int $author = null;
    private ?int $user = null;

    public function __construct(?int $author = null, ?int $user = null)
    {
        $this->author = $author;
        $this->user = $user;
    }

    public function run()
    {
        $users = User::limit(5)->get();
        $author = $this->author ?? $users->random(1)->first()->getKey();

        Consultation::factory([
            'author_id' => $author
        ], 5)->create()->each(function (Consultation $consultation) use($users) {
            $user = $this->user ?? $users->random(1)->first()->getKey();
            ConsultationUserPivot::factory([
                'consultation_id' => $consultation->getKey(),
                'user_id' => $user,
                'executed_at' => now()->format('Y-m-d H:i:s'),
                'executed_status' => ConsultationTermStatusEnum::APPROVED,
            ])->create();
        });
    }
}
