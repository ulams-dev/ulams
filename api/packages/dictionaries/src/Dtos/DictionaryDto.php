<?php

namespace Ulams\Dictionaries\Dtos;

use Ulams\Core\Dtos\Contracts\DtoContract;
use Ulams\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class DictionaryDto implements DtoContract, InstantiateFromRequest
{
    private string $name;
    private ?int $freeViewsCount;

    public function __construct(string $name, ?int $freeViewsCount)
    {
        $this->name = $name;
        $this->freeViewsCount = $freeViewsCount;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFreeViewsCount(): ?int
    {
        return $this->freeViewsCount;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'free_views_count' => $this->getFreeViewsCount(),
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new self(
            $request->input('name'),
            $request->input('free_views_count'),
        );
    }
}
