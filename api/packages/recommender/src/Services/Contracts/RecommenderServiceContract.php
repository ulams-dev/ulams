<?php

namespace Ulams\Recommender\Services\Contracts;

use Ulams\Recommender\Dto\AggregatedFrameDto;
use Ulams\Recommender\Dto\MeetRecordingDto;
use Ulams\Recommender\Dto\MeetRecordingScreenDto;
use Ulams\Recommender\Models\MeetRecording;

interface RecommenderServiceContract
{
    public function makeCourseData(int $courseId): array;

    public function completionOfCourse(int $courseId): array;

    public function makeTopicData(int $lessonId): array;

    public function matchTopicType(int $lessonId): array;

    public function aggregatedFrameSave(AggregatedFrameDto $dto): void;
    public function aggregatedFrames(string $modelType, int $modelId, int $term, int $interval);

    public function meetRecording(MeetRecordingDto $dto): MeetRecording;
    public function meetRecordingScreen(MeetRecordingScreenDto $dto): void;
}
