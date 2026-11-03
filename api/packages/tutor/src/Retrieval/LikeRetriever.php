<?php

namespace Ulams\Tutor\Retrieval;

use Illuminate\Support\Collection;
use Ulams\Tutor\Models\Chunk;

/** Fallback for databases without full-text search (the SQLite tests): counts the question's words in each chunk. */
final class LikeRetriever implements TutorRetriever
{
    public function search(int $courseId, string $question, string $language, int $limit): Collection
    {
        $words = array_values(array_unique(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY) ?: [], fn ($w) => mb_strlen($w) >= 3)));
        if ($words === []) {
            return collect();
        }

        return Chunk::query()->where('course_id', $courseId)->get()
            ->map(fn (Chunk $c) => [$c, count(array_filter($words, fn ($w) => str_contains(mb_strtolower($c->label . ' ' . $c->text), $w)))])
            ->filter(fn ($pair) => $pair[1] > 0)->sortByDesc(fn ($pair) => $pair[1])->take($limit)->map(fn ($pair) => $pair[0])->values();
    }
}
