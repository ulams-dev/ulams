<?php

namespace EscolaLms\TopicTypes\Database\Factories\TopicContent;

use EscolaLms\TopicTypes\Database\Factories\TopicContent\Components\Cmi5AuHelper;
use EscolaLms\TopicTypes\Models\TopicContent\Cmi5Au;
use Illuminate\Database\Eloquent\Factories\Factory;
use EscolaLms\Cmi5\Models\Cmi5Au as Cmi5AuModel;

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
