<?php

namespace EscolaLms\ConsultationAccess\Http\Requests\Admin;

use EscolaLms\ConsultationAccess\Http\Requests\ListConsultationAccessEnquiryRequest;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Illuminate\Support\Facades\Gate;

class AdminListConsultationAccessEnquiryRequest extends ListConsultationAccessEnquiryRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', ConsultationAccessEnquiry::class);
    }
}
