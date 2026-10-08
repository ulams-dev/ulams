<?php

namespace EscolaLms\TopicTypeGift\Export;

use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class QuizResultsExport implements WithMultipleSheets
{
    use Exportable;

    private int $courseId;
    private ?int $quizId;
    private ?int $authorId;

    public function __construct(int $courseId, ?int $quizId = null, ?int $authorId = null)
    {
        $this->courseId = $courseId;
        $this->quizId = $quizId;
        $this->authorId = $authorId;
    }

    /**
     * @return QuizResultsSheet[]
     */
    public function sheets(): array
    {
        $quizzes = GiftQuiz::query()
            ->whereHas('topic.lesson', fn (Builder $query) => $query->where('course_id', $this->courseId))
            ->when($this->quizId, fn (Builder $query) => $query->where('id', $this->quizId))
            ->when($this->authorId, fn (Builder $query) => $query->whereHas(
                'topic.lesson.course.authors',
                fn (Builder $authors) => $authors->whereKey($this->authorId)
            ))
            ->with(['questions', 'topic'])
            ->orderBy('id')
            ->get();

        $sheets = [];
        $usedTitles = [];

        foreach ($quizzes as $quiz) {
            $sheets[] = new QuizResultsSheet($quiz, $this->sheetTitle($quiz, $usedTitles));
        }

        return $sheets;
    }

    /**
     * Build a worksheet title from the quiz's topic title: strip characters Excel forbids in
     * sheet names, enforce the 31 character limit and keep it unique within the workbook.
     *
     * @param string[] $usedTitles
     */
    private function sheetTitle(GiftQuiz $quiz, array &$usedTitles): string
    {
        $base = trim(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', (string) $quiz->topic?->title));

        if ($base === '') {
            $base = 'Quiz ' . $quiz->getKey();
        }

        $title = mb_substr($base, 0, 31);

        $counter = 1;
        while (in_array($title, $usedTitles, true)) {
            $suffix = ' (' . (++$counter) . ')';
            $title = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
        }

        $usedTitles[] = $title;

        return $title;
    }
}
