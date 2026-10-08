<?php

namespace EscolaLms\Dictionaries\Models;

use EscolaLms\Dictionaries\Database\Factories\DictionaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Class Dictionary
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property integer|null $free_views_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Collection|DictionaryWord[] $dictionaryWords
 */
class Dictionary extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function dictionaryWords(): HasMany
    {
        return $this->hasMany(DictionaryWord::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'dictionary_user')
            ->using(DictionaryUser::class)->withPivot(['end_date']);
    }

    protected static function newFactory(): DictionaryFactory
    {
        return DictionaryFactory::new();
    }
}
