<?php

namespace Ulams\TopicTypes\Database\Factories\TopicContent;

use Ulams\Scorm\Services\Contracts\ScormServiceContract;
use Ulams\TopicTypes\Database\Factories\TopicContent\Components\ScormScoHelper;
use Ulams\TopicTypes\Models\TopicContent\ScormSco;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
use Peopleaps\Scorm\Model\ScormScoModel;

class ScormScoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ScormSco::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $scormSco = ScormScoModel::inRandomOrder()->first();
        return [
            'value' => isset($scormSco) ? $scormSco->id : ScormScoHelper::getScormSco()->id,
        ];
    }
}
