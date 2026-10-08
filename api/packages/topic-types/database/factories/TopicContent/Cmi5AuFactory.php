<?php

namespace Ulams\TopicTypes\Database\Factories\TopicContent;

use Ulams\TopicTypes\Database\Factories\TopicContent\Components\Cmi5AuHelper;
use Ulams\TopicTypes\Models\TopicContent\Cmi5Au;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ulams\Cmi5\Models\Cmi5Au as Cmi5AuModel;

class Cmi5AuFactory extends Factory
{
    protected $model = Cmi5Au::class;

    public function definition()
    {
        $cmi5Au = Cmi5AuModel::inRandomOrder()->first();
        return [
            'value' => isset($cmi5Au) ? $cmi5Au->id : Cmi5AuHelper::getCmi5Au()->getKey(),
        ];
    }
}
