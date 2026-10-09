<?php

namespace Ulams\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A tenant creation or deletion requested through the platform API. Platform database only.
 *
 * @property string $id
 * @property string $kind create|delete
 * @property string $tenant_slug
 * @property string $status queued|running|succeeded|failed
 * @property ?array $steps list of {name, status, startedAt, finishedAt, error}
 * @property ?array $input
 * @property ?string $error
 * @property ?int $requested_by
 * @property ?\Illuminate\Support\Carbon $started_at
 * @property ?\Illuminate\Support\Carbon $finished_at
 */
class TenantOperation extends Model
{
    public const CREATE = 'create';
    public const DELETE = 'delete';

    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';

    public const ACTIVE = [self::QUEUED, self::RUNNING];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'tenant_operations';

    protected $fillable = ['id', 'kind', 'tenant_slug', 'status', 'steps', 'input', 'error', 'requested_by', 'started_at', 'finished_at'];

    protected $casts = ['steps' => 'array', 'input' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    /**
     * @param list<string> $steps names, in order
     */
    public static function start(string $kind, string $slug, array $steps, ?int $userId, array $input = []): self
    {
        return self::query()->create([
            'id' => strtolower((string) Str::ulid()),
            'kind' => $kind,
            'tenant_slug' => $slug,
            'status' => self::QUEUED,
            'steps' => array_map(fn (string $name) => ['name' => $name, 'status' => 'pending', 'startedAt' => null, 'finishedAt' => null, 'error' => null], $steps),
            'input' => $input,
            'requested_by' => $userId,
        ]);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function markRunning(): void
    {
        $this->forceFill(['status' => self::RUNNING, 'started_at' => now()])->save();
    }

    /**
     * Records the provisioner's report: `running` opens a step (closing the previous one),
     * `done` closes it, `skipped` marks a step that was already finished.
     */
    public function recordStep(string $name, string $state): void
    {
        $steps = $this->steps ?? [];
        $now = now()->toIso8601String();
        foreach ($steps as $i => $step) {
            if ($step['name'] !== $name && $step['status'] === 'running') {
                $steps[$i]['status'] = 'succeeded';
                $steps[$i]['finishedAt'] = $now;
            }
        }
        $index = array_search($name, array_column($steps, 'name'), true);
        if ($index === false) {
            $steps[] = ['name' => $name, 'status' => 'pending', 'startedAt' => null, 'finishedAt' => null, 'error' => null];
            $index = count($steps) - 1;
        }
        match ($state) {
            'running' => $steps[$index] = ['status' => 'running', 'startedAt' => $now] + $steps[$index],
            'done' => $steps[$index] = ['status' => 'succeeded', 'finishedAt' => $now] + $steps[$index],
            'skipped' => $steps[$index] = ['status' => 'skipped', 'finishedAt' => $now] + $steps[$index],
            default => null,
        };
        $this->steps = $steps;
        $this->save();
    }

    public function markSucceeded(): void
    {
        $steps = $this->steps ?? [];
        $now = now()->toIso8601String();
        foreach ($steps as $i => $step) {
            if ($step['status'] === 'running') {
                $steps[$i]['status'] = 'succeeded';
                $steps[$i]['finishedAt'] = $now;
            } elseif ($step['status'] === 'pending') {
                $steps[$i]['status'] = 'skipped';
            }
        }
        $this->forceFill(['status' => self::SUCCEEDED, 'steps' => $steps, 'error' => null, 'finished_at' => now()])->save();
    }

    public function markFailed(string $message): void
    {
        $steps = $this->steps ?? [];
        $now = now()->toIso8601String();
        foreach ($steps as $i => $step) {
            if ($step['status'] === 'running') {
                $steps[$i]['status'] = 'failed';
                $steps[$i]['finishedAt'] = $now;
                $steps[$i]['error'] = mb_substr($message, 0, 1000);
            }
        }
        $this->forceFill(['status' => self::FAILED, 'steps' => $steps, 'error' => mb_substr($message, 0, 2000), 'finished_at' => now()])->save();
    }
}
