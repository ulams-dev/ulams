<?php

namespace EscolaLms\TopicTypeGift\Export;

use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

class QuizResultsSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStrictNullComparison
{
    private GiftQuiz $quiz;
    private string $title;

    public function __construct(GiftQuiz $quiz, string $title)
    {
        $this->quiz = $quiz;
        $this->title = $title;
    }

    public function collection(): Collection
    {
        $attempts = QuizAttempt::query()
            ->where('topic_gift_quiz_id', $this->quiz->getKey())
            ->with(['answers', 'user'])
            ->orderBy('user_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // Attempt number is a per-student sequence (1st, 2nd, ... attempt of that student).
        $attemptNumbers = [];

        return $attempts->map(function (QuizAttempt $attempt) use (&$attemptNumbers) {
            // Reuse the quiz (with its questions already loaded) so the model accessors
            // below do not trigger a query per attempt.
            $attempt->setRelation('giftQuiz', $this->quiz);

            $attemptNumbers[$attempt->user_id] = ($attemptNumbers[$attempt->user_id] ?? 0) + 1;

            return [
                'first_name' => $attempt->user?->first_name,
                'last_name' => $attempt->user?->last_name,
                'email' => $attempt->user?->email,
                'attempt_number' => $attemptNumbers[$attempt->user_id],
                'ended_at' => $attempt->isEnded() ? $attempt->end_at?->format('Y-m-d H:i') : null,
                'result_score' => $attempt->result_score,
                'max_score' => $attempt->max_score,
                'result_percent' => $attempt->result_percent,
                'is_passed' => $this->passedLabel($attempt->is_passed),
            ];
        });
    }

    public function headings(): array
    {
        return [
            __('First name'),
            __('Last name'),
            __('Email'),
            __('Attempt number'),
            __('Completed at'),
            __('Score'),
            __('Max score'),
            __('Result (%)'),
            __('Passed'),
        ];
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * The attempt is_passed accessor returns null when the attempt has not ended or the
     * quiz has no min_pass_score threshold - shown as a dash in those cases.
     */
    private function passedLabel(?bool $isPassed): string
    {
        if ($isPassed === null) {
            return '-';
        }

        return $isPassed ? __('Yes') : __('No');
    }
}
