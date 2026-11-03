<?php

namespace Ulams\LearnerInsights\Signals;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** One signal about to be recorded. Serialisable, so it can travel in a queued job. */
final class SignalData
{
    public function __construct(
        public readonly int $userId,
        public readonly string $type,
        public readonly string $source,
        public readonly string $sourceRef,
        public readonly CarbonInterface $occurredAt,
        public readonly ?int $courseId = null,
        public readonly ?int $topicId = null,
        public readonly ?int $questionId = null,
        public readonly ?float $value = null,
        public readonly array $data = [],
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'userId' => $this->userId,
            'type' => $this->type,
            'source' => $this->source,
            'sourceRef' => $this->sourceRef,
            'occurredAt' => $this->occurredAt->toIso8601String(),
            'courseId' => $this->courseId,
            'topicId' => $this->topicId,
            'questionId' => $this->questionId,
            'value' => $this->value,
            'data' => $this->data,
        ];
    }

    public static function fromArray(array $a): self
    {
        return new self(
            $a['userId'], $a['type'], $a['source'], $a['sourceRef'], CarbonImmutable::parse($a['occurredAt']),
            $a['courseId'] ?? null, $a['topicId'] ?? null, $a['questionId'] ?? null, $a['value'] ?? null, $a['data'] ?? [],
        );
    }
}
