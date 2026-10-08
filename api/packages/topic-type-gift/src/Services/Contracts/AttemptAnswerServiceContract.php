<?php

namespace Ulams\TopicTypeGift\Services\Contracts;

use Ulams\TopicTypeGift\Dtos\AdminUpdateAttemptAnswerDto;
use Ulams\TopicTypeGift\Dtos\SaveAllAttemptAnswersDto;
use Ulams\TopicTypeGift\Dtos\SaveAttemptAnswerDto;
use Ulams\TopicTypeGift\Models\AttemptAnswer;
use Illuminate\Support\Collection;

interface AttemptAnswerServiceContract
{
    public function saveAnswer(SaveAttemptAnswerDto $dto): AttemptAnswer;
    public function saveAllAnswers(SaveAllAttemptAnswersDto $dto): Collection;
    public function adminUpdate(int $id, AdminUpdateAttemptAnswerDto $dto): AttemptAnswer;
}
