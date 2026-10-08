<?php

namespace EscolaLms\ConsultationAccess\Jobs\Strategies;

class SpaceTitleRelatedToTopicStrategy extends DefaultSpaceTitleStrategy
{
    public function getTitle(): string
    {
        // @phpstan-ignore-next-line
        return $this->enquiry->related->lesson->course->title . ' ' . parent::getTitle();
    }
}
