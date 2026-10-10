<?php

namespace Ulams\CourseBuilder\Quality;

use Ulams\CourseBuilder\Publish\MarkdownAccessibility;

/** Deterministic accessibility of the generated Markdown: heading order, link text, image alternatives, table headers. */
final class AccessibilityCritic
{
    /** @return array<int,array{elementId:string,problem:string}> */
    public function check(array $lesson): array
    {
        $issues = [];
        foreach ($lesson['blocks'] ?? [] as $b => $block) {
            foreach (MarkdownAccessibility::check((string) $block['markdown']) as $problem) {
                $issues[] = ['elementId' => $block['id'], 'problem' => 'Block ' . ($b + 1) . ': ' . $problem];
            }
        }

        return $issues;
    }
}
