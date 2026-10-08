<?php

namespace EscolaLms\ConsultationAccess\Jobs\Strategies;

use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Illuminate\Support\Carbon;

class DefaultSpaceTitleStrategy implements SpaceTitleStrategy
{
    protected ConsultationAccessEnquiry $enquiry;

    public function __construct(ConsultationAccessEnquiry $enquiry)
    {
        $this->enquiry = $enquiry;
    }

    public function getTitle(): string
    {
        return sprintf(
            '%s (%d) %s',
            $this->enquiry->user->name,
            $this->enquiry->user->getKey(),
            Carbon::make($this->enquiry->consultationUserTerm->executed_at)->format('d-m-Y'),
        );
    }
}
