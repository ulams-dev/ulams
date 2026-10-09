<?php

namespace Ulams\CourseBuilder\Pipeline;

use InvalidArgumentException;
use Ulams\CourseBuilder\Blueprint\Blueprint;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Version;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Ui\Surfaces;

/**
 * Stage 2: learning objectives and outline, proposed as a version the author reviews as a diff
 * (the objectives gate). Approve (with inline objective edits) starts generation; reject with a
 * comment regenerates with the comment.
 */
final class OutlineService
{
    public function __construct(
        private readonly Llm $llm,
        private readonly PromptContext $context,
        private readonly VersionService $versions,
        private readonly Surfaces $surfaces,
        private readonly EventLog $events,
    ) {
    }

    public function generate(Session $session, Run $run, ?string $comment = null, ?Version $previous = null): Version
    {
        $known = $this->context->knownFragments($session);
        $brief = $session->brief;
        $blocks = [...$this->context->nativePdfBlocks($session), $this->context->sourceBlock($session), $this->context->contextBlock($session)];
        $instruction = 'Propose the course outline with learning objectives for this brief.';
        if ($comment !== null && $previous !== null) {
            $instruction .= "\n<previous_outline>" . json_encode(PromptContext::compactOutline($previous->document), JSON_UNESCAPED_UNICODE) . '</previous_outline>'
                . "\n<author_feedback>" . PromptContext::esc($comment) . '</author_feedback>';
        }
        $blocks[] = $this->context->instruction($instruction, ['totalMinutes' => $brief['totalMinutes'], 'lessonMinutes' => $brief['lessonMinutes']]);

        $result = $this->llm->generate($session, $run, 'outline', $blocks, fn (array $data) => self::validate($data, $known, (int) $brief['totalMinutes']));

        $doc = $this->document($session, $result->data);
        $version = $this->versions->create($session, $doc, 'outline', 'ai', Version::PROPOSED, $previous, $comment ?? 'Proposed outline', null, $result->callIds);
        $session->current_version_id = $version->id;
        $session->status = Session::OUTLINE_REVIEW;
        $session->title = $doc['course']['title'];
        $session->save();

        $this->events->text($session, $run, (string) $result->data['message']);
        $this->surfaces->outline($session, $run, $version, $previous?->document, 'proposed', $comment);

        return $version;
    }

    /** @return string[] */
    public static function validate(array $data, array $known, int $targetMinutes): array
    {
        $errors = [];
        $total = 0;
        foreach ($data['course']['objectives'] ?? [] as $i => $o) {
            $errors = [...$errors, ...Checks::citations($o['citations'] ?? [], $known, "course/objectives/{$i}")];
        }
        foreach ($data['modules'] ?? [] as $m => $module) {
            foreach ($module['lessons'] ?? [] as $l => $lesson) {
                $where = "modules/{$m}/lessons/{$l}";
                $total += (int) ($lesson['minutes'] ?? 0);
                $errors = [...$errors, ...Checks::citations($lesson['citations'] ?? [], $known, $where)];
                if (($lesson['objectives'] ?? []) === []) {
                    $errors[] = "{$where}: needs at least one learning objective";
                }
                foreach ($lesson['objectives'] ?? [] as $o => $objective) {
                    $errors = [...$errors, ...Checks::citations($objective['citations'] ?? [], $known, "{$where}/objectives/{$o}")];
                }
                foreach (['title', 'summary'] as $field) {
                    $errors = [...$errors, ...Checks::markup((string) ($lesson[$field] ?? ''), "{$where}/{$field}")];
                }
            }
        }
        if ($targetMinutes > 0 && ($total < $targetMinutes * 0.85 || $total > $targetMinutes * 1.15)) {
            $errors[] = sprintf('lesson minutes add up to %d; the brief asks for %d (±15%%). Adjust lesson lengths.', $total, $targetMinutes);
        }

        return $errors;
    }

    /** Model output → blueprint with server-assigned ids. */
    public function document(Session $session, array $data): array
    {
        $objective = fn (array $o) => ['id' => Blueprint::newId(), 'text' => trim($o['text']), 'citations' => array_values(array_unique($o['citations']))];
        $doc = Blueprint::empty(trim($data['course']['title']), (string) ($session->brief['language'] ?? 'en'));
        $doc['sources'] = $session->sources()->where('status', 'ready')->get()
            ->map(fn ($s) => ['id' => $s->id, 'title' => (string) ($s->metadata['title'] ?? $s->original_name), 'fragmentCount' => (int) ($s->metadata['fragments'] ?? 0)])->all();
        $doc['course'] += array_filter([
            'subtitle' => trim((string) ($data['course']['subtitle'] ?? '')) ?: null,
            'description' => trim((string) ($data['course']['description'] ?? '')) ?: null,
            'audience' => mb_substr((string) ($session->brief['audience'] ?? ''), 0, 300) ?: null,
        ]);
        $doc['course']['objectives'] = array_map($objective, $data['course']['objectives'] ?? []);
        foreach ($data['modules'] as $module) {
            $doc['modules'][] = array_filter([
                'id' => Blueprint::newId(),
                'title' => trim($module['title']),
                'summary' => trim((string) ($module['summary'] ?? '')) ?: null,
                'lessons' => array_map(fn ($l) => [
                    'id' => Blueprint::newId(),
                    'title' => trim($l['title']),
                    'minutes' => max(1, (int) $l['minutes']),
                    'objectives' => array_map($objective, $l['objectives']),
                    'citations' => array_values(array_unique($l['citations'])),
                    'contentType' => 'richtext',
                    'status' => 'planned',
                    'blocks' => [],
                    'quiz' => null,
                    'flags' => [],
                ] + (trim((string) ($l['summary'] ?? '')) !== '' ? ['summary' => trim((string) $l['summary'])] : []), $module['lessons']),
            ], fn ($v) => $v !== null);
        }

        return $doc;
    }

    /**
     * Approves the proposal, applying inline objective edits as an author version on top.
     *
     * @param array<int,array{objectiveId:string,text:string}> $edits
     */
    public function approve(Session $session, ?Run $run, Version $version, array $edits, int $userId): Version
    {
        if ($version->kind !== 'outline') {
            throw new InvalidArgumentException('Not an outline proposal.');
        }
        $this->versions->approve($version, $userId);
        $approved = $version;
        if ($edits !== []) {
            $doc = $version->document;
            foreach ($edits as $edit) {
                $doc = self::editObjective($doc, (string) ($edit['objectiveId'] ?? ''), trim((string) ($edit['text'] ?? '')));
            }
            $approved = $this->versions->create($session, $doc, 'outline', 'author', Version::APPROVED, $version, 'Objectives edited by the author', null, [], $userId);
        }
        $this->versions->setCurrent($session, $approved);
        $this->surfaces->outline($session, $run, $approved, $version->parent?->document, 'approved');
        $this->surfaces->close($session, "outline-{$version->id}");

        return $approved;
    }

    public function reject(Session $session, ?Run $run, Version $version, int $userId): void
    {
        $this->versions->reject($version, $userId);
        $this->surfaces->outline($session, $run, $version, $version->parent?->document, 'rejected');
    }

    public static function editObjective(array $doc, string $id, string $text): array
    {
        if ($text === '' || mb_strlen($text) > 400) {
            throw new InvalidArgumentException('An objective needs 1–400 characters.');
        }
        if (Checks::markup($text, 'objective') !== []) {
            throw new InvalidArgumentException('Objectives are plain text.');
        }
        $found = false;
        foreach ($doc['course']['objectives'] as $i => $o) {
            if ($o['id'] === $id) {
                $doc['course']['objectives'][$i]['text'] = $text;
                $found = true;
            }
        }
        foreach ($doc['modules'] as $m => $module) {
            foreach ($module['lessons'] as $l => $lesson) {
                foreach ($lesson['objectives'] as $o => $objective) {
                    if ($objective['id'] === $id) {
                        $doc['modules'][$m]['lessons'][$l]['objectives'][$o]['text'] = $text;
                        $found = true;
                    }
                }
            }
        }
        if (!$found) {
            throw new InvalidArgumentException('Unknown objective.');
        }

        return $doc;
    }
}
