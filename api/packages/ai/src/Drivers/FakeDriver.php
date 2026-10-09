<?php

namespace Ulams\Ai\Drivers;

use Closure;
use Ulams\Ai\Contracts\LlmDriver;
use Ulams\Ai\Dto\DriverRequest;
use Ulams\Ai\Dto\DriverResponse;
use Ulams\Ai\Dto\Usage;
use Ulams\Ai\Exceptions\DriverException;
use Ulams\Ai\Fake\CassetteStore;
use Ulams\Ai\Fake\FakeResponders;

/**
 * Replays cassettes; in `synthetic` mode falls back to a registered responder. Tests can also
 * queue responses for a task with `queue()` (consumed before cassettes).
 */
final class FakeDriver implements LlmDriver
{
    /** @var array<string,array<int,DriverResponse|Closure(DriverRequest):DriverResponse>> */
    private array $queued = [];

    /** @var DriverRequest[] */
    private array $sent = [];

    public function __construct(
        private readonly CassetteStore $cassettes,
        private readonly FakeResponders $responders,
        private readonly string $mode = 'synthetic',
    ) {
    }

    public function name(): string
    {
        return 'fake';
    }

    public function send(DriverRequest $request): DriverResponse
    {
        $this->sent[] = $request;

        if (!empty($this->queued[$request->task])) {
            $next = array_shift($this->queued[$request->task]);

            return $next instanceof Closure ? $next($request) : $next;
        }

        $replay = $this->cassettes->load($request);
        if ($replay !== null) {
            return $replay;
        }

        if ($this->mode === 'synthetic' && $this->responders->has($request->task)) {
            $data = $this->responders->respond($request);
            $text = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $in = $request->estimatedInputTokens();

            return new DriverResponse(
                (string) $text,
                $request->model,
                'end_turn',
                new Usage($in, (int) ceil(strlen((string) $text) / 4)),
                'synthetic',
            );
        }

        throw new DriverException(
            sprintf('No cassette for task "%s" (prompt v%d) at %s', $request->task, $request->promptVersion, $this->cassettes->path($request)),
            missingCassette: true,
        );
    }

    /** Queue a response (or a closure building one) for the next call of a task. */
    public function queue(string $task, DriverResponse|Closure $response): self
    {
        $this->queued[$task][] = $response;

        return $this;
    }

    /** Queue a successful JSON answer for a task. */
    public function queueJson(string $task, array|string $data, string $model = 'fake-model', ?Usage $usage = null): self
    {
        $text = is_string($data) ? $data : (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->queue($task, new DriverResponse($text, $model, 'end_turn', $usage ?? new Usage(100, 50), 'queued'));
    }

    /** @return DriverRequest[] */
    public function sent(): array
    {
        return $this->sent;
    }

    public function cassettes(): CassetteStore
    {
        return $this->cassettes;
    }
}
