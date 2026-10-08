<?php

namespace EscolaLms\TopicTypeGift\Http\Requests\Admin;

use EscolaLms\TopicTypeGift\Enum\TopicTypeGiftPermissionEnum;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AdminExportQuizResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('list', QuizAttempt::class);
    }

    public const FORMAT_XLSX = 'xlsx';
    public const FORMAT_XLS = 'xls';

    public const SUPPORTED_FORMATS = [self::FORMAT_XLSX, self::FORMAT_XLS];

    /**
     * Normalise the format before validation so both "XLSX" and an empty
     * "format=" behave predictably (case-insensitive, empty falls back to the default).
     */
    protected function prepareForValidation(): void
    {
        $format = $this->get('format');

        if (is_string($format)) {
            $format = strtolower(trim($format));
            $this->merge(['format' => $format === '' ? null : $format]);
        }
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer'],
            'topic_gift_quiz_id' => ['sometimes', 'nullable', 'integer'],
            'format' => ['sometimes', 'nullable', 'in:' . implode(',', self::SUPPORTED_FORMATS)],
        ];
    }

    public function getCourseId(): int
    {
        return (int) $this->get('course_id');
    }

    public function getQuizId(): ?int
    {
        return $this->get('topic_gift_quiz_id') ? (int) $this->get('topic_gift_quiz_id') : null;
    }

    /**
     * The requested spreadsheet format, defaulting to XLSX. Validation already
     * restricts the value to SUPPORTED_FORMATS, so a missing/null value is the default.
     */
    public function getExportFormat(): string
    {
        return $this->input('format') === self::FORMAT_XLS ? self::FORMAT_XLS : self::FORMAT_XLSX;
    }

    /**
     * When the user only has the "list self" permission (tutor), scope the export
     * to courses they author - mirroring the list endpoint behaviour. Users with the
     * full "list" permission (admin) get null (no author scoping).
     */
    public function getAuthorId(): ?int
    {
        $user = $this->user();

        if ($user->can(TopicTypeGiftPermissionEnum::LIST_QUIZ_ATTEMPT)) {
            return null;
        }

        return $user->getKey();
    }
}
