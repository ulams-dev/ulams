<?php

namespace Ulams\Tenancy\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Tenancy\Exceptions\EntryPointException;
use Ulams\Tenancy\Http\Controllers\Swagger\EntryPointSwagger;
use Ulams\Tenancy\Http\Requests\EntryPointReadRequest;
use Ulams\Tenancy\Http\Requests\EntryPointRequest;
use Ulams\Tenancy\Services\Contracts\EntryPointGatewayContract;
use Ulams\Tenancy\Support\EntryPointRules;
use Ulams\Tenancy\Support\EntryTargets;
use Ulams\Tenancy\Support\TenantContext;

/**
 * The branded entry points of this academy (ADR 0195, permission `entry_points_manage`). The academy is the one
 * this process serves, never a request value; the table lives on the platform and is reached through the
 * gateway. An entry point never grants access: it only changes what the learner front shows first.
 */
class EntryPointController extends UlamsBaseController implements EntryPointSwagger
{
    public function __construct(private readonly EntryPointGatewayContract $entryPoints)
    {
    }

    public function index(EntryPointReadRequest $request): JsonResponse
    {
        return $this->guarded(fn (string $slug) => $this->sendResponse($this->entryPoints->list($slug), __('Entry points')));
    }

    public function store(EntryPointRequest $request): JsonResponse
    {
        $data = $request->validated();
        $errors = EntryPointRules::targetErrors((string) $data['kind'], $data['target'] ?? null);
        $errors = $errors ?: EntryTargets::missing((string) $data['kind'], $data['target'] ?? null);
        if ($errors !== []) {
            return $this->invalid($errors);
        }

        return $this->guarded(fn (string $slug) => $this->sendResponse($this->entryPoints->create($slug, $data, $this->actor($request)), __('Entry point created'), 201));
    }

    public function show(EntryPointReadRequest $request, string $entryPoint): JsonResponse
    {
        return $this->guarded(fn (string $slug) => $this->sendResponse($this->entryPoints->show($slug, $entryPoint), __('Entry point')));
    }

    public function update(EntryPointRequest $request, string $entryPoint): JsonResponse
    {
        $data = $request->validated();

        return $this->guarded(function (string $slug) use ($data, $entryPoint, $request) {
            if (array_key_exists('target', $data) && $data['target'] !== null) {
                $current = $this->entryPoints->show($slug, $entryPoint);
                $errors = EntryPointRules::targetErrors((string) $current['kind'], $data['target']);
                $errors = $errors ?: EntryTargets::missing((string) $current['kind'], $data['target']);
                if ($errors !== []) {
                    return $this->invalid($errors);
                }
            }

            return $this->sendResponse($this->entryPoints->update($slug, $entryPoint, $data, $this->actor($request)), __('Entry point updated'));
        });
    }

    public function destroy(EntryPointReadRequest $request, string $entryPoint): JsonResponse
    {
        return $this->guarded(function (string $slug) use ($entryPoint, $request) {
            $this->entryPoints->delete($slug, $entryPoint, $this->actor($request));

            return $this->sendSuccess(__('Entry point deleted'));
        });
    }

    public function audit(EntryPointReadRequest $request): JsonResponse
    {
        return $this->guarded(fn (string $slug) => $this->sendResponse($this->entryPoints->audit($slug, min(500, max(1, (int) $request->query('limit', 100)))), __('Entry point audit trail')));
    }

    /** Runs one gateway call for this process's academy and maps a refusal to its HTTP answer. */
    private function guarded(callable $call): JsonResponse
    {
        $slug = TenantContext::slug();
        abort_if($slug === null, 404);

        try {
            return $call($slug);
        } catch (EntryPointException $e) {
            return $e->status === 422 ? $this->invalid($e->errors, $e->getMessage()) : $this->sendError($e->getMessage(), $e->status);
        } catch (RuntimeException $e) {
            report($e);

            return $this->sendError(__('Entry points are unavailable right now. Try again.'), 503);
        }
    }

    /** @param array<string, list<string>> $errors */
    private function invalid(array $errors, string $message = 'The given data was invalid.'): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], 422);
    }

    /** @return array{id: int|null, email: string|null} */
    private function actor(Request $request): array
    {
        $user = $request->user();

        return ['id' => $user?->getKey(), 'email' => $user?->email];
    }
}
