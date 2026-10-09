<?php

namespace Ulams\Interactive\Observers;

use Illuminate\Validation\ValidationException;
use Ulams\Interactive\Enums\CompletionRule;
use Ulams\Interactive\Models\InteractivePackage;
use Ulams\Interactive\Models\InteractiveTopic;

/**
 * A topic can only play steps that exist in the version it plays (the pinned one, or the current
 * one). A topic pins the current version unless it says `follow_latest` (ADR 0086), so an upload
 * never changes what learners see without the author's action.
 */
class InteractiveTopicObserver
{
    public function saving(InteractiveTopic $topic): void
    {
        /** @var InteractivePackage|null $package */
        $package = InteractivePackage::query()->find($topic->value);
        if ($package === null) {
            return;
        }

        if ($topic->follow_latest) {
            $topic->version = null;
        } elseif ($topic->version === null) {
            $topic->version = $package->current_version;
        }

        $version = $package->version($topic->version ?? $package->current_version);
        if ($version === null) {
            throw ValidationException::withMessages(['version' => [sprintf('Version %d of this package does not exist.', $topic->version)]]);
        }

        $steps = $version->stepIds();
        foreach (['start_step', 'end_step'] as $field) {
            $step = $topic->{$field};
            if ($step !== null && $step !== '' && !in_array($step, $steps, true)) {
                throw ValidationException::withMessages([$field => [sprintf('The step "%s" does not exist in version %d of the package.', $step, $version->version)]]);
            }
            if ($step === '') {
                $topic->{$field} = null;
            }
        }
        if ($topic->start_step !== null && $topic->end_step !== null && array_search($topic->start_step, $steps, true) > array_search($topic->end_step, $steps, true)) {
            throw ValidationException::withMessages(['end_step' => ['The end step comes before the start step.']]);
        }
        if ($topic->completion_rule === CompletionRule::ON_SCORE->value && $topic->pass_score === null) {
            throw ValidationException::withMessages(['pass_score' => ['A pass score is required for the on_score rule.']]);
        }
    }
}
