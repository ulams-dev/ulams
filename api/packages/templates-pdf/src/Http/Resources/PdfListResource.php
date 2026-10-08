<?php

namespace Ulams\TemplatesPdf\Http\Resources;

use Ulams\TemplatesPdf\Models\FabricPDF;
use Ulams\TemplatesPdf\Parsers\VarsParser;
use Illuminate\Http\Resources\Json\JsonResource;

class PdfListResource extends JsonResource
{
    public function __construct(FabricPDF $pdf)
    {
        $this->resource = $pdf;
    }

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'template' => $this->template,
            'user_id' => $this->user_id,
            'vars' => VarsParser::parseVars($this->vars),
            'assignable_type' => $this->assignable_type,
            'assignable_id' => $this->assignable_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
