<?php

namespace Ulams\KnowledgeExport\Services;

use Ulams\CourseBuilder\Ingestion\Fragmenter;

/**
 * Splits Markdown segments into chunks along headings with the same rules the Living Course uses for
 * source documents (`Fragmenter`): fenced code is never split, sections are packed between the
 * configured token bounds. Each segment carries the citations of the text it came from.
 */
final class Chunker
{
    private Fragmenter $fragmenter;

    public function __construct(?int $minTokens = null, ?int $maxTokens = null)
    {
        $this->fragmenter = new Fragmenter(
            $minTokens ?? (int) config('knowledge_export.min_tokens', 150),
            $maxTokens ?? (int) config('knowledge_export.max_tokens', 600),
        );
    }

    /**
     * @param list<array{text:string,citations:list<string>}> $segments
     * @return list<array{heading_path:list<string>,text:string,tokens:int,citations:list<string>}>
     */
    public function chunks(array $segments): array
    {
        $out = [];
        foreach ($segments as $segment) {
            $text = trim($segment['text']);
            if ($text === '') {
                continue;
            }
            $split = $this->fragmenter->split($text);
            foreach ($split['fragments'] as $i => $fragment) {
                $body = $fragment['text'];
                // a lone leading "# Title" is the document title for the fragmenter; the reader still needs it
                if ($i === 0 && $split['title'] !== null) {
                    $body = '# ' . $split['title'] . "\n\n" . $body;
                }
                $out[] = [
                    'heading_path' => array_values(array_map('strval', $fragment['heading_path'])),
                    'text' => $body,
                    'tokens' => Fragmenter::tokens($body),
                    'citations' => $segment['citations'],
                ];
            }
            if ($split['fragments'] === [] && $split['title'] !== null) {
                $body = '# ' . $split['title'];
                $out[] = ['heading_path' => [], 'text' => $body, 'tokens' => Fragmenter::tokens($body), 'citations' => $segment['citations']];
            }
        }

        return $out;
    }
}
