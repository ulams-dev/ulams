<?php

namespace EscolaLms\AssignWithoutAccount\Events;

use EscolaLms\Cart\Contracts\Productable;
use EscolaLms\Core\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UnassignProductable
{
    use Dispatchable, SerializesModels;

    private User $user;
    private Productable $productable;

    public function __construct(User $user, Productable $productable)
    {
        $this->user = $user;
        $this->productable = $productable;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getProductable(): Productable
    {
        return $this->productable;
    }
}
