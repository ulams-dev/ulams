<?php

namespace Ulams\LivingCourse\Analysis;

use Ulams\Ai\Dto\ContentBlock;
use Ulams\LivingCourse\Models\ProposalItem;

/**
 * What the analysis needs from the builder of a model request: the groups to analyse, their items and
 * the request for one group. `UpdateRequest` reads source revisions, `ImproveRequest` aggregated learner
 * evidence (ADR 0181).
 */
interface AnalysisRequest
{
    /** @return ProposalItem[] the analysable items of a group in document order */
    public function items(string $groupKey, ?array $only = null): array;

    /** @return string[] group keys in order of importance */
    public function groupKeys(): array;

    /**
     * @param ProposalItem[] $items
     * @param array<int,array<string,string>> $feedback unsupported claims of a previous attempt (grounding check)
     * @return array{blocks:ContentBlock[],expected:array<string,array{type:string,node:array,removedOnly:bool}>,known:array<string,string>,objectiveIds:string[]}
     */
    public function build(string $groupKey, array $items, ?string $authorRequest = null, array $feedback = []): array;
}
