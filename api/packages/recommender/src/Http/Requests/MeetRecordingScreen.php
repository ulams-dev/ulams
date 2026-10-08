<?php

namespace Ulams\Recommender\Http\Requests;

use Ulams\Consultations\Models\Consultation;
use Ulams\Webinar\Models\Webinar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class MeetRecordingScreen extends FormRequest
{
    public function authorize(): bool
    {
        $modelType = $this->get('model_type');

        $modelClass = match ($modelType) {
            'consultation' => Consultation::class,
            'webinar' => Webinar::class,
            default => null,
        };

        if (!$modelClass) {
            return false;
        }

        return Gate::allows('create', $modelClass);
    }

    public function rules(): array
    {
        return [
            'model_type' => ['required', 'in:consultation,webinar'],
            'model_id' => ['required', 'integer'],
            'term' => ['required'],
            'files' => ['array', 'min:1'],
            'files.*.file' => ['required'],
            'files.*.timestamp' => ['required'],
        ];
    }
}
