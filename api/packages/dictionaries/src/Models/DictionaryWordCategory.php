<?php

namespace EscolaLms\Dictionaries\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Class DictionaryWordCategory
 *
 * @property int $dictionary_word_id
 * @property int $category_id
 */
class DictionaryWordCategory extends Pivot
{
}
