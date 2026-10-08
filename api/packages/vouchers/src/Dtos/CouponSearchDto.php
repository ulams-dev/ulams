<?php

namespace EscolaLms\Vouchers\Dtos;

use Illuminate\Support\Carbon;

class CouponSearchDto
{
    protected ?string $name;
    protected ?string $code;
    protected ?string $type;
    protected ?Carbon $active_from;
    protected ?Carbon $active_to;
    protected ?int    $per_page;

    public function __construct(?string $name = null, ?string $code = null, ?string $type = null, ?Carbon $active_from = null, ?Carbon $active_to  = null, ?int $per_page = null)
    {
        $this->name = $name;
        $this->code = $code;
        $this->type = $type;
        $this->active_from = $active_from;
        $this->active_to = $active_to;
        $this->per_page = $per_page;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getActiveFrom(): ?Carbon
    {
        return $this->active_from;
    }

    public function getActiveTo(): ?Carbon
    {
        return $this->active_to;
    }

    public function getPerPage(): ?int
    {
        return $this->per_page;
    }
}
