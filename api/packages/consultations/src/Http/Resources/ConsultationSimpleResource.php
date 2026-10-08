<?php

namespace EscolaLms\Consultations\Http\Resources;

use Carbon\Carbon;
use EscolaLms\Auth\Traits\ResourceExtandable;
use EscolaLms\Consultations\Services\Contracts\ConsultationServiceContract;
use EscolaLms\ModelFields\Enum\MetaFieldVisibilityEnum;
use EscolaLms\ModelFields\Facades\ModelFields;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsultationSimpleResource extends JsonResource
{
    use ResourceExtandable;

    public function toArray($request)
    {
        $consultationServiceContract = app(ConsultationServiceContract::class);
        $fields = [
            'id' => $this->resource->id,
            'created_at' => Carbon::make($this->resource->created_at),
            'updated_at' => Carbon::make($this->resource->updated_at),
            'active_from' => Carbon::make($this->resource->active_from),
            'active_to' => Carbon::make($this->resource->active_to),
            'name' => $this->resource->name,
            'author' => $this->resource->author ? ConsultationAuthorResource::make($this->resource->author) : null,
            'status' => $this->resource->status,
            'description' => $this->resource->description,
            'short_desc' => $this->resource->short_desc,
            'duration' => $this->resource->getDuration(),
            'image_path' => $this->resource->image_path,
            'image_url' => $this->resource->image_url,
            'logotype_path' => $this->resource->logotype_path,
            'logotype_url' => $this->resource->logotype_url,
            'proposed_terms' => ConsultationProposedTermResource::collection($consultationServiceContract->filterProposedTerms($this->resource->getKey(), $this->resource->proposedTerms)),
            'busy_terms' => ConsultationTermResource::collection($consultationServiceContract->getBusyTermsFormatDate($this->resource->getKey())),
            'categories' => $this->resource->categories,
            'max_session_students' => $this->resource->max_session_students,
            'teachers' => ConsultationAuthorResource::collection($this->resource->teachers),
            'analyze_enabled' => $this->resource->analyze_enabled,
            ...ModelFields::getExtraAttributesValues($this->resource, MetaFieldVisibilityEnum::PUBLIC)
        ];
        return self::apply($fields, $this);
    }
}
