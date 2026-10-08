<?php

namespace EscolaLms\CoursesImportExport\Http\Resources;

use EscolaLms\CoursesImportExport\Enums\CoursesImportExportEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseScormScoExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     *
     * @return array
     */
    public function toArray($request): array
    {
        $scormSco = $this->resource->scormSco;
        return [
            'id' => $scormSco->getKey(),
            'uuid' => $scormSco ? $scormSco->uuid : null,
            'identifier' => $scormSco ? $scormSco->identifier : null,
            'scorm_file' => CoursesImportExportEnum::SCORM_FILE,
        ];
    }
}
