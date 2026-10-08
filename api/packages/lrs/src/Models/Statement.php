<?php

namespace Ulams\Lrs\Models;

use Ulams\Lrs\Database\Factories\StatementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Statement extends \Trax\XapiStore\Stores\Statements\Statement
{
    use HasFactory;

    protected static function newFactory(): StatementFactory
    {
        return StatementFactory::new();
    }
}
