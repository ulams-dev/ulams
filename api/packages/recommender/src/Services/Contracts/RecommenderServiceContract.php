<?php

namespace EscolaLms\Recommender\Services\Contracts;

use EscolaLms\Recommender\Dto\AggregatedFrameDto;
use EscolaLms\Recommender\Dto\MeetRecordingDto;
use EscolaLms\Recommender\Dto\MeetRecordingScreenDto;
use EscolaLms\Recommender\Models\MeetRecording;

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
