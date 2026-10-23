<?php

namespace Ulams\Identity\Http\Controllers\Scim;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Ulams\Identity\Http\Middleware\ScimAuthenticate;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\Scim\FilterCompiler;
use Ulams\Identity\Services\Scim\FilterParser;
use Ulams\Identity\Services\Scim\Schemas;
use Ulams\Identity\Services\Scim\ScimError;

/**
 * What the SCIM controllers share: the provider of the token, SCIM error and list formats, JSON body limits, ETag
 * preconditions, pagination and the `attributes` / `excludedAttributes` projection.
 */
abstract class ScimController extends Controller
{
    protected const CONTENT_TYPE = 'application/scim+json; charset=utf-8';

    protected function provider(Request $request): IdentityProvider
    {
        return $request->attributes->get(ScimAuthenticate::PROVIDER);
    }

    protected function base(Request $request): string
    {
        return $request->getSchemeAndHttpHost() . '/api/scim/v2';
    }

    /** Runs the action and renders every failure in the SCIM error format. */
    protected function run(Closure $action): JsonResponse|Response
    {
        try {
            return $action();
        } catch (ScimError $e) {
            return $this->error($e);
        } catch (ValidationException $e) {
            return $this->error(ScimError::invalidValue((string) collect($e->errors())->flatten()->first()));
        } catch (ModelNotFoundException) {
            return $this->error(new ScimError(404, null, 'The resource was not found.'));
        }
    }

    protected function error(ScimError $e): JsonResponse
    {
        return response()->json($e->toArray(), $e->status, $e->headers + ['Content-Type' => self::CONTENT_TYPE]);
    }

    /** @param array<string,mixed> $data */
    protected function resource(array $data, int $status = 200, array $headers = []): JsonResponse
    {
        $headers['Content-Type'] = self::CONTENT_TYPE;
        if (isset($data['meta']['version'])) {
            $headers['ETag'] = $data['meta']['version'];
        }
        if ($status === 201 && isset($data['meta']['location'])) {
            $headers['Location'] = $data['meta']['location'];
        }

        return response()->json($data, $status, $headers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string,mixed> the JSON object of the request body */
    protected function body(Request $request): array
    {
        $max = (int) config('ulams_identity.scim.max_body_bytes');
        $raw = $request->getContent();
        if (strlen($raw) > $max) {
            throw new ScimError(413, null, 'The request body is larger than ' . $max . ' bytes.');
        }
        $data = json_decode($raw, true, 32);
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw ScimError::invalidSyntax('The body must be a JSON object.');
        }

        return $data;
    }

    /** @param array<string,mixed> $resource */
    protected function assertMatch(Request $request, array $resource): void
    {
        $header = trim((string) $request->header('If-Match', ''));
        if ($header === '' || $header === '*') {
            return;
        }
        $current = $this->bare($resource['meta']['version'] ?? '');
        foreach (explode(',', $header) as $tag) {
            if ($this->bare($tag) === $current) {
                return;
            }
        }

        throw ScimError::preconditionFailed();
    }

    /** @param array<string,mixed> $resource */
    protected function notModified(Request $request, array $resource): bool
    {
        $header = trim((string) $request->header('If-None-Match', ''));
        if ($header === '') {
            return false;
        }
        $current = $this->bare($resource['meta']['version'] ?? '');

        return $header === '*' || in_array($current, array_map(fn ($t) => $this->bare($t), explode(',', $header)), true);
    }

    private function bare(string $tag): string
    {
        return trim(preg_replace('/^\s*W\//', '', trim($tag)) ?? '', " \t\"");
    }

    /**
     * RFC 7644 3.4.2: filtered, paged list as a ListResponse. `startIndex` is 1-based and below 1 means 1, `count`
     * defaults to the configured page and is capped at the maximum, `count=0` returns only `totalResults`.
     * Ordered by id: the server does not support `sortBy`.
     *
     * @param Builder<\Illuminate\Database\Eloquent\Model> $query
     * @param array<string,array<string,mixed>> $attributes filterable attributes (FilterCompiler)
     * @param array<string,mixed> $params filter, startIndex, count, attributes, excludedAttributes
     * @param Closure(\Illuminate\Database\Eloquent\Model):array<string,mixed> $present
     */
    protected function listing(Builder $query, array $attributes, array $params, string $orderColumn, Closure $present): JsonResponse
    {
        $filter = $params['filter'] ?? null;
        if ($filter !== null) {
            if (!is_string($filter)) {
                throw ScimError::invalidFilter('The filter must be a string.');
            }
            $compiler = new FilterCompiler($attributes);
            $compiler->apply($query, (new FilterParser())->parse($filter));
        }
        $max = (int) config('ulams_identity.scim.max_count');
        $start = max(1, $this->integer($params['startIndex'] ?? 1, 'startIndex'));
        $count = $this->integer($params['count'] ?? config('ulams_identity.scim.default_count'), 'count');
        $count = max(0, min($max, $count));

        $total = (clone $query)->toBase()->getCountForPagination();
        $items = $count === 0 ? collect() : $query->orderBy($orderColumn)->offset($start - 1)->limit($count)->get();
        $resources = $items->map(fn ($model) => $this->project($present($model), $params['attributes'] ?? null, $params['excludedAttributes'] ?? null))->all();

        $body = ['schemas' => [Schemas::LIST], 'totalResults' => $total, 'startIndex' => $start, 'itemsPerPage' => count($resources), 'Resources' => $resources];

        return response()->json($body, 200, ['Content-Type' => self::CONTENT_TYPE], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function integer(mixed $value, string $name): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?[0-9]{1,9}$/', $value)) {
            return (int) $value;
        }

        throw ScimError::invalidValue("{$name} must be an integer.");
    }

    /**
     * `attributes` keeps the named attributes (plus id and schemas), `excludedAttributes` drops them; both take
     * comma-separated names, sub-attributes as `name.givenName` and the enterprise extension by its URN prefix.
     *
     * @param array<string,mixed> $resource
     * @return array<string,mixed>
     */
    protected function project(array $resource, mixed $attributes, mixed $excluded): array
    {
        $only = $this->names($attributes);
        $drop = $this->names($excluded);
        if ($only === [] && $drop === []) {
            return $resource;
        }
        $out = [];
        foreach ($resource as $key => $value) {
            $lower = strtolower((string) $key);
            if (in_array($lower, ['id', 'schemas'], true)) {
                $out[$key] = $value;
                continue;
            }
            if ($only !== []) {
                $kept = $this->pick($lower, $value, $only);
                if ($kept !== null) {
                    $out[$key] = $kept;
                }
                continue;
            }
            $rest = $this->strip($lower, $value, $drop);
            if ($rest !== null) {
                $out[$key] = $rest;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function names(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $prefix = strtolower(Schemas::ENTERPRISE) . ':';
        $out = [];
        foreach (preg_split('/[\s,]+/', strtolower(trim($value))) ?: [] as $name) {
            if ($name !== '') {
                $out[] = str_starts_with($name, $prefix) ? strtolower(Schemas::ENTERPRISE) . '.' . substr($name, strlen($prefix)) : $name;
            }
        }

        return $out;
    }

    private function pick(string $key, mixed $value, array $only): mixed
    {
        $subs = [];
        foreach ($only as $name) {
            if ($name === $key) {
                return $value;
            }
            if (str_starts_with($name, $key . '.')) {
                $subs[] = substr($name, strlen($key) + 1);
            }
        }
        if ($subs === [] || !is_array($value)) {
            return null;
        }
        $filter = static fn (array $item) => array_filter($item, fn ($k) => in_array(strtolower((string) $k), $subs, true), ARRAY_FILTER_USE_KEY);

        return array_is_list($value) ? array_map(fn ($i) => is_array($i) ? $filter($i) : $i, $value) : $filter($value);
    }

    private function strip(string $key, mixed $value, array $drop): mixed
    {
        $subs = [];
        foreach ($drop as $name) {
            if ($name === $key) {
                return null;
            }
            if (str_starts_with($name, $key . '.')) {
                $subs[] = substr($name, strlen($key) + 1);
            }
        }
        if ($subs === [] || !is_array($value)) {
            return $value;
        }
        $filter = static fn (array $item) => array_filter($item, fn ($k) => !in_array(strtolower((string) $k), $subs, true), ARRAY_FILTER_USE_KEY);

        return array_is_list($value) ? array_map(fn ($i) => is_array($i) ? $filter($i) : $i, $value) : $filter($value);
    }
}
