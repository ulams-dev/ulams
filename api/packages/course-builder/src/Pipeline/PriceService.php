<?php

namespace Ulams\CourseBuilder\Pipeline;

use Throwable;
use Ulams\CourseBuilder\Blueprint\Checks;
use Ulams\CourseBuilder\Models\Run;
use Ulams\CourseBuilder\Models\Session;

/**
 * A suggested price for a course the author wants to sell: only when the brief says "paid" and has no
 * amount yet. Light model, nothing cited; the result is kept apart from the brief (state
 * `priceSuggestion`) and marked as a suggestion until the author confirms an amount.
 */
final class PriceService
{
    public function __construct(private readonly Llm $llm, private readonly PromptContext $context)
    {
    }

    public function needed(Session $session): bool
    {
        $pricing = (array) ($session->brief['pricing'] ?? []);

        return ($pricing['mode'] ?? 'free') === 'paid' && empty($pricing['amountMinor']);
    }

    /**
     * @return array{amountMinor:int,currency:string,rationale:string,suggested:bool}|null null when
     *         no suggestion is needed, the model is off or the call failed (the author sets the price)
     */
    public function suggest(Session $session, Run $run, array $outline): ?array
    {
        if (!$this->needed($session) || !$this->llm->enabled()) {
            return null;
        }
        $currency = strtoupper((string) config('ulams_payments.default_currency', 'USD'));
        try {
            $result = $this->llm->generate($session, $run, 'price', [
                $this->context->sourceBlock($session),
                $this->context->contextBlock($session, $outline),
                $this->context->instruction("Suggest a price for this course in {$currency} (minor units).", ['currency' => $currency]),
            ], fn (array $data) => Checks::markup((string) ($data['rationale'] ?? ''), 'rationale'));
        } catch (Throwable) {
            return null;
        }
        $suggestion = ['amountMinor' => (int) $result->data['amountMinor'], 'currency' => $currency, 'rationale' => (string) $result->data['rationale'], 'suggested' => true];
        $session->putState('priceSuggestion', $suggestion);
        $session->save();

        return $suggestion;
    }
}
