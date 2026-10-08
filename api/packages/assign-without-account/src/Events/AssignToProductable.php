<?php

namespace Ulams\AssignWithoutAccount\Events;

use Ulams\Cart\Contracts\Productable;
use Ulams\Core\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssignToProductable
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
