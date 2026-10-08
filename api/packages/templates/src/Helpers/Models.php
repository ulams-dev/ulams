<?php

namespace EscolaLms\Templates\Helpers;

use Illuminate\Database\Eloquent\Model;

class Models
{
    public static function getMorphClassFromModelClass(?string $class): ?string
    {
        if (is_null($class) || !is_a($class, Model::class, true)) {
            return null;
        }
        $model = new $class();
        assert($model instanceof Model);
        return $model->getMorphClass();
    }
}
