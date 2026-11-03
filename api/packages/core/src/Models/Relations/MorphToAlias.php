<?php

namespace Ulams\Core\Models\Relations;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;

/**
 * A MorphTo for owners that cast their type column with MorphType. Eager loading groups the owners by the type
 * attribute, which the cast shows as a class name; Eloquent's enforced morph map only resolves aliases there, so the
 * stored (alias) value is used instead.
 *
 * @extends MorphTo<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model>
 */
class MorphToAlias extends MorphTo
{
    protected function buildDictionary(EloquentCollection $models)
    {
        $isAssociative = Arr::isAssoc($models->all());

        foreach ($models as $key => $model) {
            $type = $model->getAttributes()[$this->morphType] ?? null;
            if (!$type) {
                continue;
            }
            $morphTypeKey = $this->getDictionaryKey($type);
            $foreignKeyKey = $this->getDictionaryKey($model->{$this->foreignKey});

            if ($morphTypeKey === null || $foreignKeyKey === null) {
                continue;
            }

            if ($isAssociative) {
                $this->dictionary[$morphTypeKey][$foreignKeyKey][$key] = $model;
            } else {
                $this->dictionary[$morphTypeKey][$foreignKeyKey][] = $model;
            }
        }
    }
}
