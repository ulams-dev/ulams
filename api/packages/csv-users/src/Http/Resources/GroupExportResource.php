<?php

namespace EscolaLms\CsvUsers\Http\Resources;

use EscolaLms\Auth\Traits\ResourceExtandable;
use EscolaLms\CsvUsers\Models\Group;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupExportResource extends JsonResource
{
    use ResourceExtandable;

    public function __construct(Group $group)
    {
        parent::__construct($group);
    }

    public function getResource(): Group
    {
        return $this->resource;
    }

    public function toArray($request): array
    {
        $fields =  [
            'name' => $this->getResource()->name,
            'registerable' => $this->getResource()->registerable,
        ];

        return self::apply($fields, $this);
    }
}
