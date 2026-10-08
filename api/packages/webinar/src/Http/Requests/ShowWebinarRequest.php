<?php

namespace EscolaLms\Webinar\Http\Requests;

use Illuminate\Support\Facades\Gate;

class ShowWebinarRequest extends BaseWebinarRequest
{
    public function authorize(): bool
    {
        return Gate::allows('read', $this->getWebinar());
    }
}
