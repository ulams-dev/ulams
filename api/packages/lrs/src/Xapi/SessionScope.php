<?php

namespace Ulams\Lrs\Xapi;

use Illuminate\Http\Request;
use Ulams\Lrs\Events\AuCompletionReported;
use Ulams\Lrs\Http\Middleware\AuthenticateXapiAccess;

/**
 * What a cmi5 session token (ADR 0046) may do in the LRS: write and read the statements and the
 * state of its own registration, read profiles, write nothing else. Requests authenticated any
 * other way pass through untouched.
 */
final class SessionScope
{
    private const COMPLETED = [
        'http://adlnet.gov/expapi/verbs/completed',
        'http://adlnet.gov/expapi/verbs/passed',
    ];

    /** @param array{i: int, u: int, r: string, a: int|null, x: string, exp: int} $claims */
    private function __construct(private readonly array $claims)
    {
    }

    public static function of(Request $request): ?self
    {
        $claims = $request->attributes->get(AuthenticateXapiAccess::SESSION_ATTRIBUTE);

        return is_array($claims) ? new self($claims) : null;
    }

    public function registration(): string
    {
        return $this->claims['r'];
    }

    /**
     * @param list<array<string, mixed>> $statements
     */
    public function assertOwnStatements(array $statements): void
    {
        foreach ($statements as $statement) {
            $registration = is_array($statement) ? ($statement['context']['registration'] ?? null) : null;

            if (!is_string($registration) || strtolower($registration) !== $this->registration()) {
                throw XapiException::forbidden('The statement is not for the registration of this session.');
            }
        }
    }

    /**
     * @param array<string, mixed> $statement
     */
    public function isOwnStatement(array $statement): bool
    {
        $registration = $statement['context']['registration'] ?? null;

        return is_string($registration) && strtolower($registration) === $this->registration();
    }

    /**
     * Tells the course packages that the AU of this session completed or passed.
     *
     * @param list<array<string, mixed>> $statements
     */
    public function reportCompletion(array $statements): void
    {
        if ($this->claims['a'] === null) {
            return;
        }

        foreach ($statements as $statement) {
            $verb = strtolower((string) ($statement['verb']['id'] ?? ''));

            if (in_array($verb, self::COMPLETED, true)) {
                AuCompletionReported::dispatch($this->claims['u'], $this->claims['a'], $this->registration());

                return;
            }
        }
    }

    /**
     * The state resource is per registration; profiles are read-only for an AU.
     */
    public function assertDocumentAccess(string $resource, string $method, ?string $registration): void
    {
        if ($resource === 'state') {
            if ($registration === null || strtolower($registration) !== $this->registration()) {
                throw XapiException::forbidden('The state is limited to the registration of this session.');
            }

            return;
        }

        if (!in_array($method, ['GET', 'HEAD'], true)) {
            throw XapiException::forbidden('A cmi5 session may not write profiles.');
        }
    }
}
