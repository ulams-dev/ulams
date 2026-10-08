<?php

namespace App\Models;

use Ulams\Cart\Contracts\Productable;
use Ulams\Cart\Contracts\ProductableTrait;

class StationaryEvent extends \Ulams\StationaryEvents\Models\StationaryEvent implements Productable
{
    use ProductableTrait;
}
