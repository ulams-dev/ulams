<?php

namespace Ulams\CourseBuilder\Apply;

use Ulams\CourseBuilder\Blueprint\Blueprint;

/**
 * The course landing and course header as documents in the `@ulams/ui` catalogue format
 * (`{component, props, children}`, ADR 0008), built by code from the blueprint: outcomes from the
 * objectives, syllabus from the outline, who it is for from the brief, FAQ from the metadata.
 * Theme, links and ids that only exist after the apply are data bindings (`$data`).
 */
final class LandingDocument
{
    public static function landing(array $doc, array $brief): array
    {
        $course = $doc['course'];
        $stats = Blueprint::stats($doc);
        $facts = array_values(array_filter([
            ['label' => 'Length', 'value' => $stats['minutes'] . ' min'],
            ['label' => 'Lessons', 'value' => (string) $stats['lessons']],
            ['label' => 'Level', 'value' => ucfirst((string) ($brief['level'] ?? ''))],
            $stats['questions'] > 0 ? ['label' => 'Quiz questions', 'value' => (string) $stats['questions']] : null,
        ], fn ($f) => $f !== null && $f['value'] !== ''));

        $main = [
            ['component' => 'Hero', 'props' => array_filter([
                'variant' => 'editorial',
                'eyebrow' => 'Course',
                'title' => self::cut($course['title'], 90),
                'subtitle' => isset($course['subtitle']) ? self::cut($course['subtitle'], 160) : null,
                'body' => isset($course['description']) ? self::cut($course['description'], 500) : null,
                'primaryCta' => ['label' => 'Start learning', 'href' => ['$data' => '/course/startHref', '$default' => '/']],
                'facts' => array_slice($facts, 0, 4),
            ], fn ($v) => $v !== null)],
        ];
        if (($course['objectives'] ?? []) !== []) {
            $main[] = ['component' => 'FeatureList', 'props' => [
                'variant' => 'checks',
                'title' => 'What you will be able to do',
                'items' => array_map(fn ($o) => ['icon' => 'check', 'title' => self::cut($o['text'], 80), 'text' => self::cut($o['text'], 240)], array_slice($course['objectives'], 0, 8)),
            ]];
        }
        $main[] = ['component' => 'Syllabus', 'props' => array_filter([
            'variant' => 'folio',
            'title' => 'Syllabus',
            'intro' => isset($brief['audience']) ? self::cut('For ' . lcfirst((string) $brief['audience']) . '.', 600) : null,
            'totalLabel' => self::cut(sprintf('%d modules · %d lessons · %d min', $stats['modules'], $stats['lessons'], $stats['minutes']), 80),
            'lessons' => array_map(fn ($m) => array_filter([
                'title' => self::cut($m['title'], 160),
                'summary' => isset($m['summary']) ? self::cut($m['summary'], 400) : null,
                'minutes' => array_sum(array_map(fn ($l) => (int) $l['minutes'], $m['lessons'])),
                'topics' => array_map(fn ($l) => ['title' => self::cut($l['title'], 160), 'format' => 'reading', 'minutes' => (int) $l['minutes']], $m['lessons']),
            ], fn ($v) => $v !== null), $doc['modules']),
        ], fn ($v) => $v !== null)];
        if (($course['faq'] ?? []) !== []) {
            $main[] = ['component' => 'Faq', 'props' => [
                'title' => 'Questions',
                'items' => array_map(fn ($f) => ['q' => self::cut($f['question'], 200), 'a' => self::cut($f['answer'], 1000)], array_slice($course['faq'], 0, 12)),
            ]];
        }

        return [
            'component' => 'Page',
            'props' => [
                'theme' => ['$data' => '/site/theme', '$default' => 'platform'],
                'title' => self::cut($course['seo']['title'] ?? $course['title'], 70),
                'description' => self::cut($course['seo']['description'] ?? ($course['subtitle'] ?? $course['title']), 160),
            ],
            'children' => [
                ['component' => 'SiteHeader', 'props' => ['brand' => ['$data' => '/site/brand', '$default' => self::cut($course['title'], 60)]]],
                ['component' => 'Main', 'children' => $main],
                ['component' => 'SiteFooter', 'props' => ['brand' => ['$data' => '/site/brand', '$default' => self::cut($course['title'], 60)]]],
            ],
        ];
    }

    public static function header(array $doc, array $brief): array
    {
        $course = $doc['course'];
        $stats = Blueprint::stats($doc);

        return ['component' => 'CourseHeader', 'props' => array_filter([
            'variant' => 'editorial',
            'eyebrow' => 'Course',
            'title' => self::cut($course['title'], 160),
            'subtitle' => isset($course['subtitle']) ? self::cut($course['subtitle'], 200) : null,
            'summary' => isset($course['description']) ? self::cut($course['description'], 600) : null,
            'facts' => [
                ['label' => 'Length', 'value' => $stats['minutes'] . ' min'],
                ['label' => 'Level', 'value' => ucfirst((string) ($brief['level'] ?? 'beginner'))],
                ['label' => 'Language', 'value' => strtoupper((string) ($course['language'] ?? ''))],
            ],
        ], fn ($v) => $v !== null)];
    }

    private static function cut(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return mb_strlen($text) <= $max ? $text : rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }
}
