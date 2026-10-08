<?php

namespace Database\Seeders;

use Ulams\Auth\Models\User;
use Ulams\Cart\Models\Order;
use Ulams\Cart\Models\OrderItem;
use Ulams\Consultations\Enum\ConsultationTermStatusEnum;
use Ulams\Consultations\Models\Consultation;
use Ulams\Consultations\Models\ConsultationProposedTerm;
use Ulams\Consultations\Models\ConsultationTerm;
use Ulams\Consultations\Models\ConsultationUserPivot;
use Illuminate\Database\Seeder;

class ConsultationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $statuses = collect([
            ConsultationTermStatusEnum::REPORTED,
            ConsultationTermStatusEnum::APPROVED,
            ConsultationTermStatusEnum::REJECT,
        ]);
        $modifierDate = collect(['hours', 'days', 'minutes']);
        $users = User::factory(5)->create();
        Consultation::factory(10)
            ->has(ConsultationProposedTerm::factory(3), 'proposedTerms')
            ->create()
            ->each(fn (Consultation $consultation) =>
                ConsultationUserPivot::factory(3, [
                    'consultation_id' => $consultation->getKey(),
                    'user_id' => $users->random(1)->first(),
                    'executed_status' => $statuses->random(1)->first(),
                    'executed_at' => now()
                        ->modify('+' . random_int(1, 10) . ' ' . $modifierDate->random(1)->first())
                        ->format('Y-m-d H:i:s'),
                ])->create()
            );
    }
}
