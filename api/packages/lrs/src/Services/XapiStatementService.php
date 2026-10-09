<?php

namespace Ulams\Lrs\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ulams\Lrs\Models\Access;
use Ulams\Lrs\Models\Statement;
use Ulams\Lrs\Services\Contracts\XapiStatementServiceContract;
use Ulams\Lrs\Xapi\Agent;
use Ulams\Lrs\Xapi\StatementValidator;
use Ulams\Lrs\Xapi\XapiException;

class XapiStatementService implements XapiStatementServiceContract
{
    public const DEFAULT_LIMIT = 100;
    public const MAX_LIMIT = 500;

    public function store(array $statements, Access $access): array
    {
        if ($statements === []) {
            throw XapiException::badRequest('No statement given.');
        }

        $prepared = [];
        $ids = [];

        foreach (array_values($statements) as $i => $statement) {
            $errors = StatementValidator::errors($statement);
            if ($errors) {
                throw XapiException::badRequest(sprintf('Statement %d is invalid: %s', $i, implode(' ', $errors)));
            }

            $statement['id'] = strtolower($statement['id'] ?? (string) Str::uuid());
            if (isset($ids[$statement['id']])) {
                throw XapiException::badRequest("Statement id [{$statement['id']}] is used twice in the batch.");
            }
            $ids[$statement['id']] = true;
            $prepared[] = $statement;
        }

        $ownerId = $access->ownerId();

        return DB::transaction(function () use ($prepared, $access, $ownerId) {
            $stored = $this->storedTimestamp();
            $ids = [];

            foreach ($prepared as $statement) {
                $ids[] = $statement['id'];

                $existing = Statement::query()
                    ->where('uuid', $statement['id'])
                    ->where('owner_id', $ownerId)
                    ->first();

                if ($existing) {
                    if (!$this->sameStatement((array) json_decode(json_encode($existing->data), true), $statement)) {
                        throw XapiException::conflict("Statement [{$statement['id']}] already exists with different content.");
                    }
                    continue;
                }

                $statement['stored'] = $stored;
                $statement['timestamp'] ??= $stored;
                $statement['version'] ??= '1.0.0';
                $statement['authority'] ??= $this->authority($access);

                Statement::query()->create([
                    'uuid' => $statement['id'],
                    'data' => $statement,
                    'voided' => false,
                    'pending' => false,
                    'validation' => Statement::VALIDATION_PASSED,
                    'owner_id' => $ownerId,
                    'entity_id' => $access->client?->entity_id,
                    'client_id' => $access->client_id,
                    'access_id' => $access->getKey(),
                ]);

                if (StatementValidator::isVoiding($statement)) {
                    $this->void($statement['object']['id'], $ownerId);
                }
            }

            return $ids;
        });
    }

    public function find(string $id, Access $access, bool $voided = false): array
    {
        if (!StatementValidator::isUuid($id)) {
            throw XapiException::badRequest('The statement id must be a UUID.');
        }

        $statement = Statement::query()
            ->where('uuid', strtolower($id))
            ->where('owner_id', $access->ownerId())
            ->where('voided', $voided)
            ->first();

        if (!$statement) {
            throw XapiException::notFound();
        }

        return $this->toArray($statement);
    }

    public function query(array $filters, Access $access, string $baseUrl): array
    {
        $limit = $this->limit($filters['limit'] ?? null);
        $ascending = filter_var($filters['ascending'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = Statement::query()
            ->where('owner_id', $access->ownerId())
            ->where('voided', false);

        $this->applyFilters($query, $filters);

        if (isset($filters['more'])) {
            if (!ctype_digit((string) $filters['more'])) {
                throw XapiException::badRequest('Invalid [more] parameter.');
            }
            $query->where('id', $ascending ? '>' : '<', (int) $filters['more']);
        }

        $records = $query->orderBy('id', $ascending ? 'asc' : 'desc')->limit($limit + 1)->get();
        $hasMore = $records->count() > $limit;
        $records = $records->take($limit);

        $more = '';
        if ($hasMore) {
            $params = array_intersect_key($filters, array_flip(['agent', 'verb', 'activity', 'registration', 'since', 'until', 'limit', 'ascending', 'format']));
            $params['more'] = $records->last()->getKey();
            $more = $baseUrl . '?' . http_build_query($params);
        }

        return [
            'statements' => $records->map(fn (Statement $statement) => $this->toArray($statement))->values()->all(),
            'more' => $more,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['agent'])) {
            $ifi = Agent::ifi(Agent::fromParameter($filters['agent']));
            $query->whereJsonContains('data->actor', $ifi);
        }
        if (isset($filters['verb'])) {
            if (!StatementValidator::isIri($filters['verb'])) {
                throw XapiException::badRequest('The [verb] parameter must be an IRI.');
            }
            $query->where('data->verb->id', $filters['verb']);
        }
        if (isset($filters['activity'])) {
            if (!StatementValidator::isIri($filters['activity'])) {
                throw XapiException::badRequest('The [activity] parameter must be an IRI.');
            }
            $query->where('data->object->id', $filters['activity']);
        }
        if (isset($filters['registration'])) {
            if (!StatementValidator::isUuid($filters['registration'])) {
                throw XapiException::badRequest('The [registration] parameter must be a UUID.');
            }
            $query->where('data->context->registration', strtolower($filters['registration']));
        }
        foreach (['since' => '>', 'until' => '<='] as $param => $operator) {
            if (isset($filters[$param])) {
                if (!StatementValidator::isTimestamp($filters[$param])) {
                    throw XapiException::badRequest("The [$param] parameter must be an ISO 8601 timestamp.");
                }
                $query->where('created_at', $operator, Carbon::parse($filters[$param])->utc());
            }
        }
    }

    private function limit(mixed $limit): int
    {
        if ($limit === null || $limit === '' || (string) $limit === '0') {
            return self::DEFAULT_LIMIT;
        }
        if (!ctype_digit((string) $limit)) {
            throw XapiException::badRequest('The [limit] parameter must be a positive integer.');
        }

        return min((int) $limit, self::MAX_LIMIT);
    }

    private function void(string $targetId, ?int $ownerId): void
    {
        $target = Statement::query()
            ->where('uuid', strtolower($targetId))
            ->where('owner_id', $ownerId)
            ->first();

        // A voiding statement can not itself be voided.
        if ($target && !StatementValidator::isVoiding((array) json_decode(json_encode($target->data), true))) {
            $target->update(['voided' => true]);
        }
    }

    /**
     * Same statement for the purpose of an idempotent re-send: the properties the LRS sets
     * (stored, authority, version, timestamp when it was defaulted) are ignored.
     */
    private function sameStatement(array $stored, array $incoming): bool
    {
        $keys = ['actor', 'verb', 'object', 'result', 'context'];
        $pick = fn (array $statement) => $this->sortRecursive(array_intersect_key($statement, array_flip($keys)));

        return $pick($stored) == $pick($incoming);
    }

    private function sortRecursive(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursive($item);
            }
        }
        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function authority(Access $access): array
    {
        return [
            'objectType' => 'Agent',
            'name' => $access->client?->name ?? $access->name,
            'account' => [
                'homePage' => rtrim((string) config('app.url'), '/'),
                'name' => $access->uuid,
            ],
        ];
    }

    private function storedTimestamp(): string
    {
        return Carbon::now()->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Statement $statement): array
    {
        return (array) json_decode(json_encode($statement->data), true);
    }
}
