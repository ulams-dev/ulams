<?php

namespace EscolaLms\TopicTypeGift\Http\Requests\Admin;

use EscolaLms\TopicTypeGift\Enum\TopicTypeGiftPermissionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * @OA\Schema(
 *      schema="AdminImportGiftQuestionsRequest",
 *      required={"topic_gift_quiz_id", "file"},
 *      @OA\Property(
 *          property="topic_gift_quiz_id",
 *          description="topic_gift_quiz_id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="file",
 *          description="file",
 *          type="file"
 *      )
 * )
 *
 */
class AdminImportGiftQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(TopicTypeGiftPermissionEnum::IMPORT_GIFT_QUIZ_QUESTION);
    }

    public function rules(): array
    {
        return [
            'topic_gift_quiz_id' => ['required', 'integer', 'exists:topic_gift_quizzes,id'],
            'file' => ['required', 'file'],
        ];
    }

    public function getQuizId(): int
    {
        return $this->get('topic_gift_quiz_id');
    }

    public function getFile(): UploadedFile
    {
        return $this->file('file');
    }
}
