<?php

namespace Ulams\Core\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Ulams\Core\Models\Casts\MorphType;
use Ulams\Core\Models\MorphTypeBuilder;
use Ulams\Core\Models\Relations\MorphToAlias;

/**
 * For a model that owns a polymorphic relation. Name the type columns in `$morphTypeColumns`: the database stores
 * the morph alias, the attribute (and the API) keeps showing the class name, assigning either stores the alias, and
 * queries on the column accept either. See Ulams\Core\Support\MorphMap.
 *
 * @property array<int, string> $morphTypeColumns
 */
trait HasMorphTypeColumns
{
    public function initializeHasMorphTypeColumns(): void
    {
        $this->mergeCasts(array_fill_keys($this->morphTypeColumns ?? [], MorphType::class));
    }

    /** @param \Illuminate\Database\Query\Builder $query */
    public function newEloquentBuilder($query): Builder
    {
        return new MorphTypeBuilder($query);
    }

    /** Eager loading groups by the stored alias, see MorphToAlias. */
    protected function newMorphTo(Builder $query, Model $parent, $foreignKey, $ownerKey, $type, $relation): MorphTo
    {
        return new MorphToAlias($query, $parent, $foreignKey, $ownerKey, $type, $relation);
    }
}
