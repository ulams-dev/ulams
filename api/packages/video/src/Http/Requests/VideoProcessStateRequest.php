<?php

namespace EscolaLms\Video\Http\Requests;

use EscolaLms\Video\Dto\VideoSearchDto;
use EscolaLms\Video\Models\Video;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class VideoProcessStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('states', Video::class);
    }

    public function rules(): array
    {
        return [];
    }

    public function criteria(): array
    {
        return VideoSearchDto::instantiateFromRequest($this)->toArray();
    }
}
