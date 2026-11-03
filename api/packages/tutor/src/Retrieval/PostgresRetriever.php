<?php

namespace Ulams\Tutor\Retrieval;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ulams\Tutor\Models\Chunk;

/** PostgreSQL full-text search over `tsv`: the words of the question OR-ed, ranked with `ts_rank_cd` (ADR 0060). */
final class PostgresRetriever implements TutorRetriever
{
    public static function configFor(string $language): string
    {
        return (string) (config('tutor.search_configs')[strtolower(substr($language, 0, 2))] ?? 'simple');
    }

    public function search(int $courseId, string $question, string $language, int $limit): Collection
    {
        // a question is natural language: any of its words may match (OR), the ranking puts the chunks
        // with the most and the rarest ones first. Words are letters and digits only, so the query cannot be injected.
        $words = array_slice(array_values(array_unique(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY) ?: [], fn ($w) => mb_strlen($w) >= 3))), 0, 12);
        if ($words === []) {
            return collect();
        }
        $rows = DB::select(
            'SELECT c.id FROM tutor_chunks c, to_tsquery(?::regconfig, ?) q WHERE c.course_id = ? AND c.tsv @@ q ORDER BY ts_rank_cd(c.tsv, q) DESC, c.id LIMIT ?',
            [self::configFor($language), implode(' | ', $words), $courseId, $limit],
        );
        $ids = array_map(fn ($r) => (int) $r->id, $rows);
        if ($ids === []) {
            return collect();
        }
        $byId = Chunk::query()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $byId[$id])->values();
    }
}
