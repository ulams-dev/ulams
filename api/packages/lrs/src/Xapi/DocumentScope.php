<?php

namespace Ulams\Lrs\Xapi;

use Illuminate\Http\Request;
use Ulams\Lrs\Models\ActivityProfile;
use Ulams\Lrs\Models\AgentProfile;
use Ulams\Lrs\Models\State;
use Ulams\Lrs\Models\XapiDocument;

/**
 * Which xAPI document (or set of documents) a request is about, built from the request
 * parameters of the State, Activity Profile and Agent Profile resources.
 */
final class DocumentScope
{
    /**
     * @param class-string<XapiDocument> $model
     * @param array<string, string|null> $keys columns other than the document id
     */
    private function __construct(
        public readonly string $model,
        public readonly array $keys,
        public readonly ?string $documentId,
    ) {
    }

    public static function state(Request $request): self
    {
        $activityId = self::iri($request, 'activityId');
        $agent = Agent::fromParameter(self::param($request, 'agent'));
        $registration = self::param($request, 'registration');

        if ($registration !== null && !StatementValidator::isUuid($registration)) {
            throw XapiException::badRequest('The [registration] parameter must be a UUID.');
        }

        return new self(State::class, [
            'activity_id' => $activityId,
            'vid' => Agent::virtualId($agent),
            'registration' => $registration !== null ? strtolower($registration) : null,
        ], self::optionalString($request, 'stateId'));
    }

    public static function activityProfile(Request $request): self
    {
        return new self(ActivityProfile::class, [
            'activity_id' => self::iri($request, 'activityId'),
        ], self::optionalString($request, 'profileId'));
    }

    public static function agentProfile(Request $request): self
    {
        $agent = Agent::fromParameter(self::param($request, 'agent'));

        return new self(AgentProfile::class, [
            'vid' => Agent::virtualId($agent),
        ], self::optionalString($request, 'profileId'));
    }

    public function requireDocumentId(): self
    {
        if ($this->documentId === null) {
            $name = $this->model::documentIdColumn() === 'state_id' ? 'stateId' : 'profileId';
            throw XapiException::badRequest("The [$name] parameter is required.");
        }

        return $this;
    }

    public function withoutDocumentId(): self
    {
        return new self($this->model, $this->keys, null);
    }

    /**
     * Column => value conditions identifying the document(s).
     *
     * @return array<string, string|null>
     */
    public function attributes(): array
    {
        return $this->documentId === null
            ? $this->keys
            : $this->keys + [$this->model::documentIdColumn() => $this->documentId];
    }

    /**
     * A request parameter: from the query string, or from a form-encoded body (the LMS
     * sends its own launch requests that way).
     */
    private static function param(Request $request, string $name): mixed
    {
        $value = $request->query($name);

        if ($value === null && !$request->isJson()) {
            $value = $request->request->get($name);
        }

        return $value;
    }

    private static function iri(Request $request, string $name): string
    {
        $value = self::param($request, $name);

        if (!StatementValidator::isIri($value)) {
            throw XapiException::badRequest("The [$name] parameter must be an IRI.");
        }

        return $value;
    }

    private static function optionalString(Request $request, string $name): ?string
    {
        $value = self::param($request, $name);

        if ($value === null) {
            return null;
        }
        if (!is_string($value) || $value === '') {
            throw XapiException::badRequest("The [$name] parameter must be a string.");
        }

        return $value;
    }
}
