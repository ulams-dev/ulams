<?php

namespace EscolaLms\Recommender\Http\Requests;

use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Webinar\Models\Webinar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TermAnalyticRequest extends FormRequest
{
    public function authorize(): bool
    {
        $modelType = $this->route('modelType');

        $modelClass = match ($modelType) {
            'consultation' => Consultation::class,
            'webinar' => Webinar::class,
            default => null,
        };

        if (!$modelClass) {
            return false;
        }

        return Gate::allows('list', $modelClass);
    }


    public function rules(): array
    {
        return [
            //
        ];
    }
}
