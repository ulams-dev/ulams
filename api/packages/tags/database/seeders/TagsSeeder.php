<?php

namespace Ulams\Tags\Database\Seeders;

use Ulams\Tags\Models\Tag;
use Illuminate\Database\Seeder;

class TagsSeeder extends Seeder
{
    public function run()
    {
        if (class_exists('Ulams\Courses\Models\Course')) {
            $course = call_user_func(['Ulams\Courses\Models\Course', 'factory'])->create();
            config(['tag_model_map.test' => 'Ulams\Courses\Models\Course']);
            $tagsData = [
                [
                    'title' => 'Bestseller',
                    'morphable_type' => 'Ulams\Courses\Models\Course',
                    'morphable_id' => $course->getKey()
                ],
                [
                    'title' => 'Nowości',
                    'morphable_type' => 'Ulams\Courses\Models\Course',
                    'morphable_id' => $course->getKey()
                ],
                [
                    'title' => 'Promocje',
                    'morphable_type' => 'Ulams\Courses\Models\Course',
                    'morphable_id' => $course->getKey()
                ],
                [
                    'title' => 'Najlepsze hity',
                    'morphable_type' => 'Ulams\Courses\Models\Course',
                    'morphable_id' => $course->getKey()
                ],
                [
                    'title' => 'Na czasie',
                    'morphable_type' => 'Ulams\Courses\Models\Course',
                    'morphable_id' => $course->getKey()
                ]
            ];
            foreach ($tagsData as $value) {
                Tag::create($value);
            }
        }
    }
}
