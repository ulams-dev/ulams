<?php

namespace Ulams\Webinar\Http\Requests;

use Ulams\Webinar\Enum\WebinarPermissionsEnum;
use Ulams\Webinar\Models\Webinar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class WebinarAssignableUserListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(WebinarPermissionsEnum::WEBINAR_CREATE, Webinar::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['string'],
        ];
    }
}
