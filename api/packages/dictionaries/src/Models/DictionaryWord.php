<?php

namespace EscolaLms\Dictionaries\Models;

use EscolaLms\Dictionaries\Database\Factories\DictionaryWordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Class DictionaryWord
 *
 * @property int $id
 * @property int $dictionary_id
 * @property string $word
 * @property string $description
 * @property array $data
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Dictionary $dictionary
 * @property-read Collection|Category[] $categories
 *
 */
class DictionaryWord extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'data' => 'array',
    ];

    public function dictionary(): BelongsTo
    {
        return $this->belongsTo(Dictionary::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'dictionary_word_category')
            ->using(DictionaryWordCategory::class);
    }

    protected static function newFactory(): DictionaryWordFactory
    {
        return DictionaryWordFactory::new();
    }
}
