<?php

namespace Ulams\Tutor\Retrieval;

use Illuminate\Support\Collection;
use Ulams\Tutor\Models\Chunk;

interface TutorRetriever
{
    /**
     * The chunks of a course that best match a question, most relevant first.
     *
     * @return Collection<int,Chunk>
     */
    public function search(int $courseId, string $question, string $language, int $limit): Collection;
}
