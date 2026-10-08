<?php

namespace EscolaLms\Categories\Dtos;

use EscolaLms\Categories\Dtos\Contracts\ModelDtoContract;
use EscolaLms\Categories\Models\Category;

class CategoryDto extends BaseDto implements ModelDtoContract
{
    protected string $name;
    protected string $iconClass;
    protected bool $isActive;
    protected int $parentId;
    protected $iconPath = false;
    protected $icon;
    protected ?string $description;
    protected ?int $order;

    public function model(): Category
    {
        return Category::newModelInstance();
    }

    public function toArray($filters = false): array
    {
        $result = $this->fillInArray($this->model()->getFillable());

        return $filters ? array_filter($result) : $result;
    }

    public function getIcon()
    {
        return $this->icon;
    }

    public function getIconPath()
    {
        if (is_null($this->getIcon()) && $this->iconPath !== false) {
            return $this->iconPath;
        }

        return false;
    }
}
