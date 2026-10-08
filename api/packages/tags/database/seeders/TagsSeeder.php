<?php

namespace EscolaLms\Tags\Database\Seeders;

use EscolaLms\Tags\Models\Tag;
use Illuminate\Database\Seeder;

class TagsSeeder extends Seeder
{
    public function run()
    {
        if (class_exists('EscolaLms\Courses\Models\Course')) {
            $course = call_user_func(['EscolaLms\Courses\Models\Course', 'factory'])->create();
            config(['tag_model_map.test' => 'EscolaLms\Courses\Models\Course']);
            $tagsData = [
                [
                    'title' => 'Bestseller',
                    'morphable_type' => 'EscolaLms\Courses\Models\Course',
                    'morphable_id' => $course->getKey()
                ],
                [
                    'title' => 'Nowości',
                    'morphable_type' => 'EscolaLms\Courses\Models\Course',
                    'morphable_id' => $course->getKey()
                ],
                [
                    'title' => 'Promocje',
                    'morphable_type' => 'EscolaLms\Courses\Models\Course',
                    'morphable_id' => $course->getKey()
                ],
                [
                    'title' => 'Najlepsze hity',
                    'morphable_type' => 'EscolaLms\Courses\Models\Course',
                    'morphable_id' => $course->getKey()
                ],
                [
                    'title' => 'Na czasie',
                    'morphable_type' => 'EscolaLms\Courses\Models\Course',
                    'morphable_id' => $course->getKey()
                ]
            ];
            foreach ($tagsData as $value) {
                Tag::create($value);
            }
        }
    }
}
