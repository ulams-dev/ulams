<?php

namespace Ulams\Core\Models\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Ulams\Core\Support\MorphMap;

/**
 * For a polymorphic `*_type` column: the database stores the morph alias (`topic.rich_text`), while the model
 * attribute, and so the API, keeps showing the class name it always showed. Assigning either form stores the alias.
 * Queries on the column itself (`where('topicable_type', ...)`) must use the alias: `MorphMap::canonical()`.
 */
class MorphType implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return is_string($value) ? MorphMap::classOf($value) : $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [$key => is_string($value) ? MorphMap::canonical($value) : $value];
    }
}
