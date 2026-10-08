<?php

namespace EscolaLms\TopicTypeGift\Services\Contracts;

use EscolaLms\TopicTypeGift\Dtos\AdminUpdateAttemptAnswerDto;
use EscolaLms\TopicTypeGift\Dtos\SaveAllAttemptAnswersDto;
use EscolaLms\TopicTypeGift\Dtos\SaveAttemptAnswerDto;
use EscolaLms\TopicTypeGift\Models\AttemptAnswer;
use Illuminate\Support\Collection;

interface AttemptAnswerServiceContract
{
    public function saveAnswer(SaveAttemptAnswerDto $dto): AttemptAnswer;
    public function saveAllAnswers(SaveAllAttemptAnswersDto $dto): Collection;
    public function adminUpdate(int $id, AdminUpdateAttemptAnswerDto $dto): AttemptAnswer;
}
