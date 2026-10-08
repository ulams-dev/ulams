<?php

namespace EscolaLms\Consultations\Jobs;

use EscolaLms\Consultations\Services\Contracts\ConsultationServiceContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReminderAboutConsultationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    private string $status;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $status)
    {
        $this->status = $status;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $consultationServiceContract = app(ConsultationServiceContract::class);
        $consultationServiceContract->reminderAboutConsultation($this->status);
    }
}
