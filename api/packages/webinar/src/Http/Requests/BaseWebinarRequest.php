<?php

namespace Ulams\Webinar\Http\Requests;

use Ulams\Webinar\Models\Webinar;
use Illuminate\Foundation\Http\FormRequest;

class BaseWebinarRequest extends FormRequest
{
    public function getWebinar(): Webinar
    {
        return Webinar::findOrFail($this->route('webinar'));
    }

    public function rules(): array
    {
        return [];
    }
}
