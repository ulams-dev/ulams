<?php

namespace Ulams\Consultations\Listeners;

use Ulams\Consultations\Events\ReminderAboutTerm;
use Ulams\Consultations\Services\Contracts\ConsultationServiceContract;

class ReminderAboutTermListener
{
    private ConsultationServiceContract $consultationServiceContract;
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(
        ConsultationServiceContract $consultationServiceContract
    ) {
        $this->consultationServiceContract = $consultationServiceContract;
    }

    /**
     * Handle the event.
     *
     * @param  ReminderAboutTerm  $event
     * @return void
     */
    public function handle(ReminderAboutTerm $event)
    {
        $this->consultationServiceContract->setReminderStatus($event->getConsultationTerm(), $event->getStatus(), $event->getConsultationUserTerm());
    }
}
