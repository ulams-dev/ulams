<?php

namespace Ulams\ModelFields\Http\Requests;

use Ulams\ModelFields\Models\Metadata;
use Ulams\Templates\Facades\Template as FacadesTemplate;
use Ulams\Templates\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use BenSampo\Enum\Rules\EnumValue;
use Ulams\ModelFields\Enum\MetaFieldTypeEnum;

class MetadataCreateOrUpdateRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('createOrUpdate', Metadata::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => ['required', 'string'],
            'type' => ['required', new EnumValue(MetaFieldTypeEnum::class)],
            'rules' => ['sometimes', 'json'],
            'extra' => ['sometimes', 'json'],
            'default' => ['nullable', 'string'],
            'class_type' => ['required', 'string'],
        ];
    }
}
