<?php

namespace Ulams\Video\Database\Factories;

use Ulams\Courses\Models\Topic;
use Ulams\Video\Models\Video;
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
