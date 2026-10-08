<?php

namespace Ulams\Images\Database\Factories;

use Ulams\Images\Models\ImageCache;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImageCacheFactory extends Factory
{
    protected $model = ImageCache::class;

    public function definition(): array
    {
        $path = $this->faker->filePath();

        return [
            'path' => $path,
            'hash_path' => sha1($path),
        ];
    }
}
