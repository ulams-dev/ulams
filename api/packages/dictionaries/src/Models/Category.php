<?php

namespace EscolaLms\Dictionaries\Models;

use EscolaLms\Categories\Models\Category as BaseCategory;
use EscolaLms\Dictionaries\Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Class EscolaLms\Dictionaries\Models\Category
 *
 * @property-read int $dictionary_words_count
 * @property-read Collection|DictionaryWord[] $dictionaryWords
 */
class Category extends BaseCategory
{
    use HasFactory;

    public function dictionaryWords(): BelongsToMany
    {
        return $this->belongsToMany(DictionaryWord::class, 'dictionary_word_category')
            ->using(DictionaryWordCategory::class);
    }

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }
}
