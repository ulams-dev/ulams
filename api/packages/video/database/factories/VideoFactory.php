<?php

namespace EscolaLms\Video\Database\Factories;

use EscolaLms\Courses\Models\Topic;
use EscolaLms\Video\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

class VideoFactory extends Factory
{
    protected $model = Video::class;

    public function definition(): array
    {
        return [
            'value' => 'video.mp4',
            'poster' => 'poster.jpg',
            'width' => 640,
            'height' => 480,
        ];
    }
}
