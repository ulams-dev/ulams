<?php

namespace Ulams\LearnerInsights\Scoring;

/**
 * Why a status was given, rendered from a message key and parameters, never from free text found in
 * the data (ADR 0058). `params` carries numbers and element labels only.
 */
final class Reason
{
    /** @param array<string,scalar> $params */
    public function __construct(
        public readonly string $rule,
        public readonly string $messageKey,
        public readonly array $params = [],
        public readonly ?string $elementKey = null,
    ) {
    }

    public function message(?string $locale = null): string
    {
        return (string) trans('learner-insights::' . $this->messageKey, $this->params, $locale);
    }

    /** @return array{rule:string,messageKey:string,params:array,elementKey:?string} */
    public function toArray(): array
    {
        return ['rule' => $this->rule, 'messageKey' => $this->messageKey, 'params' => $this->params, 'elementKey' => $this->elementKey];
    }

    public static function fromArray(array $a): self
    {
        return new self($a['rule'], $a['messageKey'], $a['params'] ?? [], $a['elementKey'] ?? null);
    }
}
