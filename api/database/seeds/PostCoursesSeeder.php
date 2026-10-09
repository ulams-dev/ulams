<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Topic;
use Ulams\H5P\Models\H5PContent;
use Ulams\TopicTypes\Models\TopicContent\H5P;
use Peopleaps\Scorm\Model\ScormScoModel;

class PostCoursesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $courses = Course::with('lessons')->get();
        // H5P contents come from the H5P service seeder (`make h5p-seed`, api/h5p src/cli/seed.ts)
        $contents = H5PContent::query()->get();
        $scormScos = ScormScoModel::all();
        
        if ( $contents->count() == 0) {
            return; 
        }
        

        foreach ($courses as $course) {
            $rnd = rand(1,2);
            switch($rnd) {
                case 1: // scorm
                    $course->scorm_sco_id = $scormScos->random()->id;
                    $course->save();
                    break;
                case 2: // sylabus
                default:
                    foreach ($course->lessons as $lesson) {
                        $content = $contents->random();                        
                        $topic = Topic::create([
                            'lesson_id'=>$lesson->id,
                            'title'=> $content->title ?: $content->main_library,
                            'order' => 0
                        ]);
                        $topicable = H5P::create([
                            'value' => $content->id
                        ]);
                        $topic->topicable()->associate($topicable)->save();
                    }
            }
        }
    }
}
