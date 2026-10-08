<?php

use EscolaLms\Consultations\Models\ConsultationUserPivot;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        ConsultationUserPivot::query()->whereNotNull('executed_at')->chunk(100, function ($consultationUsers) {
            /** @var ConsultationUserPivot $consultationUser */
            foreach ($consultationUsers as $consultationUser) {
                $consultationUser->userTerms()->create([
                    'executed_status' => $consultationUser->executed_status,
                    'executed_at' => $consultationUser->executed_at,
                    'reminder_status' => $consultationUser->reminder_status,
                ]);
            }
        });
    }

    public function down()
    {
        //
    }
};
