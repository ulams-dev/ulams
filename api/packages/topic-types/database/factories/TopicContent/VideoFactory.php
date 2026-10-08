<?php

namespace EscolaLms\TopicTypes\Database\Factories\TopicContent;

use EscolaLms\Courses\Facades\Topic;
use EscolaLms\TopicTypes\Models\TopicContent\Video;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Illuminate\Http\File;

class VideoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Video::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            //'topic_id' => $this->faker->word,
            'value' => '1.mp4',
            'poster' => 'poster.jpg',
            'width' => 640,
            'height' => 480,
        ];
    }

    public function updatePath(int $videoId): VideoFactory
    {
        return $this->state(function () use ($videoId) {
            $topic = Topic::find($videoId);
            $word = $this->faker->word;
            $filename = $topic->storage_directory . $word . '.mp4';
            $filenamePoster = $topic->storage_directory . $word . '.jpg';
            $dest = Storage::disk('public')->path($filename);
            $destPoster = Storage::disk('public')->path($filenamePoster);
            $destDir = dirname($dest);           
            $mocksPath = realpath(__DIR__.'/../../mocks');
            
            Storage::putFileAs($topic->storage_directory, new File($mocksPath . '/1.mp4'), $word . '.mp4');            
            Storage::putFileAs($topic->storage_directory, new File($mocksPath . '/poster.jpg'), $word . '.jpg');

            return [
                'value' => $filename,
                'poster' => $filenamePoster,
            ];
        });
    }
}
