<?php

namespace EscolaLms\Recommender\Http\Requests;

use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Recommender\Dto\PageDto;
use EscolaLms\Recommender\Dto\TermAnalyticsFilterListDto;
use EscolaLms\Webinar\Models\Webinar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TermAnalyticListRequest extends FormRequest
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
            'name' => ['string'],
            'order_by' => ['sometimes', 'string', 'in:id,name,term'],
            'date_from' => ['string'],
            'date_to' => ['string'],
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
        ];
    }

    public function getCriteriaDto(): TermAnalyticsFilterListDto
    {
        return TermAnalyticsFilterListDto::instantiateFromRequest($this);
    }

    public function getPaginationDto(): PageDto
    {
        return PageDto::instantiateFromRequest($this);
    }

    public function getOrderDto(): OrderDto
    {
        return OrderDto::instantiateFromRequest($this);
    }
}
