<?php

namespace Ulams\CourseBuilder\Ingestion;

use RuntimeException;
use Ulams\CourseBuilder\Models\Source;

/**
 * Converted documents to fragment rows (no database writes). Fragment ids are computed with the
 * source row id, so a new revision written into the same source keeps the ids of fragments whose
 * heading position did not change. With several documents (repositories, web pages) the first
 * element of every heading path is the file path, so ids stay stable per file.
 */
final class SourceDocumentBuilder
{
    /**
     * @param ConvertedDocument[] $documents
     * @return array{rows:array<int,array<string,mixed>>,markdown:string,tokens:int,meta:array<string,mixed>}
     */
    public function rows(Source $source, array $documents): array
    {
        if ($documents === []) {
            throw new RuntimeException('No text could be extracted from this file.');
        }
        $splitter = new Fragmenter((int) config('course_builder.fragments.min_tokens', 150), (int) config('course_builder.fragments.max_tokens', 600));
        $multi = count($documents) > 1 || $documents[0]->path !== null;
        $seen = [];
        $rows = [];
        $markdown = [];
        $title = null;
        $sections = 0;
        $meta = [];
        foreach ($documents as $document) {
            $split = $splitter->split($document->markdown, $document->pageMarks);
            $title ??= $split['title'] ?? ($document->metadata['title'] ?? null);
            $sections += $split['sections'];
            $meta += $document->metadata;
            foreach ($split['fragments'] as $fragment) {
                if ($multi) {
                    $fragment['heading_path'] = [(string) $document->path, ...$fragment['heading_path']];
                    $fragment['level'] = $fragment['level'] + 1;
                }
                // ordinal within the heading path across the document (two sections with the same path never collide)
                $pathKey = implode("\x1E", $fragment['heading_path']);
                $ordinal = $seen[$pathKey] = ($seen[$pathKey] ?? -1) + 1;
                $rows[] = $fragment + [
                    'id' => FragmentId::make($source->id, $fragment['heading_path'], $ordinal),
                    'source_id' => $source->id,
                    'file_path' => $document->path,
                    'content_hash' => hash('sha256', $fragment['text']),
                    'path_ordinal' => $ordinal,
                ];
            }
            $markdown[] = $multi ? '<!-- file: ' . $document->path . " -->\n\n" . $document->markdown : $document->markdown;
        }
        if ($rows === []) {
            throw new RuntimeException('No text could be extracted from this file.');
        }
        $joined = $multi ? implode("\n\n", $markdown) : $markdown[0];
        $title = $title ?: pathinfo($source->original_name, PATHINFO_FILENAME);
        $meta = [
            'title' => $title,
            'sections' => $sections,
            'fragments' => count($rows),
            'words' => str_word_count(strip_tags($joined)),
            'language' => SourceIngestor::guessLanguage($joined),
        ] + $meta;
        if ($multi) {
            $meta['files'] = count($documents);
        }
        $meta['title'] = $title;

        return ['rows' => $rows, 'markdown' => $joined, 'tokens' => (int) array_sum(array_column($rows, 'token_estimate')), 'meta' => $meta];
    }
}
