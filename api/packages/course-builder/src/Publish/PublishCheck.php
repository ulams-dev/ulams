<?php

namespace Ulams\CourseBuilder\Publish;

use Ulams\CourseBuilder\Apply\BlueprintApplier;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Pipeline\BriefService;
use Ulams\Pages\Models\Page;

/**
 * What stands between an applied course and "published": blocking items the author must fix, and
 * warnings the author may acknowledge (ADR 0010: the author decides; AI findings never block).
 *
 * @phpstan-type Item array{code:string,message:string,elementId?:string}
 */
final class PublishCheck
{
    public function __construct(private readonly BlueprintApplier $applier, private readonly LandingValidator $landing)
    {
    }

    /** @return array{blocking:array<int,array<string,string>>,warnings:array<int,array<string,string>>,facts:array<string,mixed>} */
    public function run(Session $session): array
    {
        $blocking = [];
        $warnings = [];
        $version = $session->currentVersion;
        $doc = $version?->document ?? [];
        $brief = (array) $session->brief;

        if ($session->course_id === null) {
            $blocking[] = ['code' => 'not_applied', 'message' => 'Apply the course to your academy before publishing it.'];
        } elseif ($session->applied_version_id !== $session->current_version_id) {
            $blocking[] = ['code' => 'version_not_applied', 'message' => 'The latest version of the course has not been applied yet. Apply it first.'];
        }
        if ($session->course_id !== null && $doc !== []) {
            foreach ($this->applier->drift($session, $doc) as $title) {
                $blocking[] = ['code' => 'drift', 'message' => "“{$title}” was edited in the admin after the last apply. Apply again and confirm overwriting it, or copy the edit into the blueprint."];
            }
        }
        $slug = $session->course_id !== null ? "course-{$session->course_id}" : null;
        $page = $slug !== null ? Page::query()->where('slug', $slug)->first() : null;
        $landingErrors = $this->landing->validate($page !== null ? json_decode((string) $page->content, true) : ($doc['pages']['landing'] ?? null));
        if ($landingErrors !== []) {
            $blocking[] = ['code' => 'landing_invalid', 'message' => 'The course landing page is not valid: ' . $landingErrors[0]];
        }
        $pricing = (array) ($brief['pricing'] ?? ['mode' => 'free']);
        if (($pricing['mode'] ?? 'free') === 'paid' && empty($pricing['amountMinor'])) {
            $blocking[] = ['code' => 'price_unconfirmed', 'message' => 'The course is paid but has no confirmed price. Set the price in the Course brief.'];
        }

        if ($doc !== []) {
            $known = array_fill_keys(Blueprint::citations($doc), true);
            foreach (Checks::blueprint($doc, $known) as $error) {
                if (str_starts_with($error, 'warning: ')) {
                    $warnings[] = ['code' => 'flagged', 'message' => substr($error, 9)];
                }
            }
            $lessonMinutes = (int) ($brief['lessonMinutes'] ?? 0);
            foreach (Blueprint::lessons($doc) as $item) {
                $lesson = $item['lesson'];
                $uncited = count(array_filter((array) ($lesson['blocks'] ?? []), fn ($b) => ($b['citations'] ?? []) === []))
                    + count(array_filter((array) ($lesson['quiz']['questions'] ?? []), fn ($q) => ($q['citations'] ?? []) === []));
                if ($uncited > 0) {
                    $warnings[] = ['code' => 'uncited', 'elementId' => (string) $lesson['id'], 'message' => "“{$lesson['title']}” has {$uncited} element(s) with no source citation."];
                }
                if ($lessonMinutes > 0 && (int) ($lesson['minutes'] ?? 0) > (int) ceil($lessonMinutes * 1.5)) {
                    $warnings[] = ['code' => 'long_lesson', 'elementId' => (string) $lesson['id'], 'message' => "“{$lesson['title']}” is {$lesson['minutes']} minutes; your brief asked for lessons of about {$lessonMinutes}."];
                }
                $problems = [];
                foreach ((array) ($lesson['blocks'] ?? []) as $block) {
                    $problems = [...$problems, ...MarkdownAccessibility::check((string) ($block['markdown'] ?? ''))];
                }
                foreach (array_unique($problems) as $problem) {
                    $warnings[] = ['code' => 'accessibility', 'elementId' => (string) $lesson['id'], 'message' => "“{$lesson['title']}”: {$problem}."];
                }
            }
        }
        $adjusted = null;
        if (isset($brief['theme']['accent'], $brief['theme']['preset'])) {
            $result = AccentContrast::adjust((string) $brief['theme']['accent'], (string) $brief['theme']['preset']);
            if ($result !== null && $result['adjusted']) {
                $adjusted = $result['value'];
                $warnings[] = ['code' => 'accent_adjusted', 'message' => "The accent {$brief['theme']['accent']} is hard to read on this theme, so the site shows {$result['value']} instead."];
            }
        }
        // critique results from the quality loop (M2.4) are added by Publish\CritiqueSource when present
        foreach (self::$extraWarnings as $source) {
            $warnings = [...$warnings, ...$source($session)];
        }

        $stats = $doc !== [] ? Blueprint::stats($doc) : [];
        $front = rtrim((string) config('course_builder.front_url'), '/');

        return [
            'blocking' => $blocking,
            'warnings' => $warnings,
            'facts' => [
                'courseId' => $session->course_id,
                'title' => $doc['course']['title'] ?? $session->title,
                'url' => $session->course_id !== null && $front !== '' ? "{$front}/courses/{$session->course_id}" : null,
                'published' => (bool) $session->stateValue('published', false),
                'price' => [
                    'mode' => $pricing['mode'] ?? 'free',
                    'amountMinor' => $pricing['amountMinor'] ?? null,
                    'currency' => $pricing['currency'] ?? null,
                    'label' => BriefService::priceLabel($pricing),
                    'suggestion' => $session->stateValue('priceSuggestion'),
                ],
                'theme' => isset($brief['theme']) ? $brief['theme'] + ['adjustedAccent' => $adjusted] : null,
                'counts' => $stats,
                'applyNotes' => (array) $session->stateValue('applyNotes', []),
                'landingValid' => $landingErrors === [],
            ],
        ];
    }

    /** @var array<string,\Closure(Session):array<int,array<string,string>>> */
    private static array $extraWarnings = [];

    /** Other parts of the builder add warnings (the critic loop adds failed critiques). */
    public static function extendWarnings(string $name, \Closure $source): void
    {
        self::$extraWarnings[$name] = $source;
    }
}
