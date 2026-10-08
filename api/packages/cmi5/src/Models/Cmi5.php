<?php

namespace EscolaLms\Cmi5\Models;

use EscolaLms\Cmi5\Database\Factories\Cmi5Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cmi5 extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function aus(): HasMany
    {
        return $this->hasMany(Cmi5Au::class);
    }

    protected static function newFactory(): Cmi5Factory
    {
        return Cmi5Factory::new();
    }
}
