<?php

namespace EscolaLms\AssignWithoutAccount\Events;

use EscolaLms\Cart\Models\Product;
use EscolaLms\Core\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssignToProduct
{
    use Dispatchable, SerializesModels;

    private User $user;
    private Product $product;

    public function __construct(User $user, Product $product)
    {
        $this->user = $user;
        $this->product = $product;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }
}
