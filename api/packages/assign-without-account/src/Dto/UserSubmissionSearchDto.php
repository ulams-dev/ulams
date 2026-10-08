<?php

namespace EscolaLms\AssignWithoutAccount\Dto;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class UserSubmissionSearchDto implements DtoContract, InstantiateFromRequest
{
    private ?string $email;
    private ?int $morphableId;
    private ?string $morphableType;
    private ?string $status;

    public function __construct(?string $email, ?int $morphableId, ?string $morphableType, ?string $status)
    {
        $this->email = $email;
        $this->morphableId = $morphableId;
        $this->morphableType = $morphableType;
        $this->status = $status;
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'morphable_id' => $this->morphableId,
            'morphable_type' => $this->morphableType,
            'status' => $this->status,
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new static(
            $request->input('email'),
            $request->input('morphable_id'),
            $request->input('morphable_type'),
            $request->input('status'),
        );
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getMorphableId(): ?int
    {
        return $this->morphableId;
    }

    public function getMorphableType(): ?string
    {
        return $this->morphableType;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
}
