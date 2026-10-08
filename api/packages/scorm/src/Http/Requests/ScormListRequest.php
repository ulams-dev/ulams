<?php

namespace Ulams\Scorm\Http\Requests;

use Ulams\Scorm\Enums\ScormPermissionsEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Peopleaps\Scorm\Model\ScormModel;

class ScormListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', ScormModel::class);
    }

    public function rules(): array
    {
        return [];
    }

    public function pageParams(): ?int
    {
        return $this->get('per_page') === null || $this->get('per_page') === "0"
            ? 0
            : $this->get('per_page');
    }

    public function searchParams(): array
    {
        $search = $this->except(['limit', 'skip', 'order', 'order_by']);

        if (!$this->user()->can(ScormPermissionsEnum::SCORM_LIST) && $this->user()->can(ScormPermissionsEnum::SCORM_LIST_OWN)) {
            $search['user_id'] = $this->user()->getKey();
        }

        return $search;
    }
}
