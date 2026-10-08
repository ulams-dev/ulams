<?php

namespace EscolaLms\Cmi5\Models;

use EscolaLms\Cmi5\Database\Factories\Cmi5AuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cmi5Au extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function cmi5(): BelongsTo
    {
        return $this->belongsTo(Cmi5::class);
    }

    protected static function newFactory(): Cmi5AuFactory
    {
        return Cmi5AuFactory::new();
    }
}
