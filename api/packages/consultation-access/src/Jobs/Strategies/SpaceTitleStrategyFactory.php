<?php

namespace EscolaLms\ConsultationAccess\Jobs\Strategies;

use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;

final class SpaceTitleStrategyFactory
{
    public static function create(ConsultationAccessEnquiry $enquiry): SpaceTitleStrategy
    {
        if(!$enquiry->related_type || !class_exists($enquiry->related_type) || !$enquiry->related) {
            return new DefaultSpaceTitleStrategy($enquiry);
        }

        switch ($enquiry->related_type){
            case \EscolaLms\Courses\Models\Topic::class:
                return new SpaceTitleRelatedToTopicStrategy($enquiry);
            case \EscolaLms\Courses\Models\Lesson::class:
                return new SpaceTitleRelatedToLessonStrategy($enquiry);
            case \EscolaLms\Courses\Models\Course::class:
                return new SpaceTitleRelatedToCourseStrategy($enquiry);
            default:
                return new DefaultSpaceTitleStrategy($enquiry);
        }
    }
}
