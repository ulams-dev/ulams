<?php

namespace Ulams\ConsultationAccess\Http\Requests\Admin;

use Ulams\ConsultationAccess\Http\Requests\ListConsultationAccessEnquiryRequest;
use Ulams\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Illuminate\Support\Facades\Gate;

class AdminListConsultationAccessEnquiryRequest extends ListConsultationAccessEnquiryRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', ConsultationAccessEnquiry::class);
    }
}
