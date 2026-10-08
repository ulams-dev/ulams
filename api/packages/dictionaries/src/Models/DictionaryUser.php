<?php

namespace EscolaLms\Dictionaries\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Class DictionaryUser
 *
 * @property int $dictionary_id
 * @property int $user_id
 * @property Carbon|null $end_date
 *
 * @property-read Dictionary $dictionary
 * @property-read User $user
 * @property-read bool $is_active
 */
class DictionaryUser extends Pivot
{
    public function dictionary(): BelongsTo
    {
        return $this->belongsTo(Dictionary::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getIsActiveAttribute(): bool
    {
        return is_null($this->end_date) || $this->end_date >= Carbon::now();
    }
}
