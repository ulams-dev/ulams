<?php

namespace Ulams\Reporting\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Ulams\Reporting\Definition\DefinitionValidator;
use Ulams\Reporting\Definition\InvalidReportDefinition;
use Ulams\Reporting\Enums\ReportingPermissionsEnum;
use Ulams\Reporting\Models\SavedReport;
use Ulams\Reporting\Support\ReportContext;

/**
 * Saved reports and who may do what with them (ADR 0194, plan 6.8). Sharing shares the definition, never the data: a
 * report always runs with the permissions of whoever runs it. The owner and anyone with `reporting_manage` see a
 * private report; a report shared with roles is also visible to everyone who holds one of them.
 */
class SavedReports
{
    public function __construct(private readonly DefinitionValidator $validator, private readonly DatasetRegistry $registry)
    {
    }

    /** The reports a viewer can see, newest first. */
    public function visibleTo(Authenticatable $viewer): Builder
    {
        $query = SavedReport::query()->orderByDesc('updated_at')->orderByDesc('id');
        if ($this->can($viewer, ReportingPermissionsEnum::MANAGE)) {
            return $query;
        }
        $roles = $this->roleNames($viewer);

        return $query->where(function (Builder $q) use ($viewer, $roles) {
            $q->where('owner_user_id', $viewer->getAuthIdentifier());
            if ($roles !== []) {
                $q->orWhere(function (Builder $shared) use ($roles) {
                    $shared->where('visibility', SavedReport::ROLES);
                    $shared->where(function (Builder $any) use ($roles) {
                        foreach ($roles as $role) {
                            $any->orWhereJsonContains('shared_roles', $role);
                        }
                    });
                });
            }
        });
    }

    public function find(string $id, Authenticatable $viewer): ?SavedReport
    {
        return $this->visibleTo($viewer)->whereKey($id)->first();
    }

    /** The owner (who still builds reports) or an administrator who manages reports of others. */
    public function canEdit(SavedReport $report, Authenticatable $viewer): bool
    {
        return $this->can($viewer, ReportingPermissionsEnum::MANAGE)
            || ((int) $report->owner_user_id === (int) $viewer->getAuthIdentifier() && $this->can($viewer, ReportingPermissionsEnum::CREATE));
    }

    /** Whether the viewer holds the permission of the report's dataset (the dataset may also be gone: then nobody can run it). */
    public function runnable(SavedReport $report, Authenticatable $viewer): bool
    {
        $dataset = $this->registry->get((string) ($report->definition['dataset'] ?? ''));

        return $dataset !== null && (new ReportContext($viewer))->can($dataset->permission());
    }

    /**
     * What is wrong with a saved definition today: a dataset or column that no longer exists, a capability that changed.
     * Empty when it still validates.
     *
     * @return list<array{path:string,message:string}>
     */
    public function problems(SavedReport $report): array
    {
        try {
            $this->validator->validate($report->definition);
        } catch (InvalidReportDefinition $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @param array<string,mixed> $attributes name, description, visibility, shared_roles, definition
     * @throws InvalidReportDefinition
     * @throws ValidationException
     */
    public function create(Authenticatable $owner, array $attributes): SavedReport
    {
        $this->checkDefinition($attributes['definition'], $owner);
        $this->checkSharing($attributes['visibility'] ?? SavedReport::PRIVATE, $attributes['shared_roles'] ?? []);

        return SavedReport::query()->create($this->attributes($attributes) + ['owner_user_id' => $owner->getAuthIdentifier(), 'visibility' => SavedReport::PRIVATE, 'feed_enabled' => false]);
    }

    /**
     * @param array<string,mixed> $attributes any of name, description, visibility, shared_roles, definition
     * @throws InvalidReportDefinition
     * @throws ValidationException
     */
    public function update(SavedReport $report, Authenticatable $viewer, array $attributes): SavedReport
    {
        if (isset($attributes['definition'])) {
            $this->checkDefinition($attributes['definition'], $viewer);
        }
        $visibility = $attributes['visibility'] ?? $report->visibility;
        $roles = array_key_exists('shared_roles', $attributes) ? $attributes['shared_roles'] : $report->shared_roles;
        $this->checkSharing($visibility, $roles ?? []);
        $report->fill($this->attributes($attributes + ['visibility' => $visibility, 'shared_roles' => $roles]))->save();

        return $report;
    }

    public function delete(SavedReport $report): void
    {
        $report->delete();
    }

    /** The reports a BI tool may read through the feed: enabled, visible to the viewer, and not above the resources of a limited token. */
    public function feedsFor(Authenticatable $viewer, ?array $tokenResources = null): Builder
    {
        $query = $this->visibleTo($viewer)->where('feed_enabled', true);
        if (!empty($tokenResources)) {
            $query->whereIn('id', $tokenResources);
        }

        return $query;
    }

    public function markRun(SavedReport $report): void
    {
        $report->forceFill(['last_run_at' => now()])->saveQuietly();
    }

    /** @param array<string,mixed> $attributes @return array<string,mixed> */
    private function attributes(array $attributes): array
    {
        $out = array_intersect_key($attributes, array_flip(['name', 'description', 'visibility', 'shared_roles', 'definition', 'feed_enabled']));
        if (($out['visibility'] ?? SavedReport::PRIVATE) === SavedReport::PRIVATE) {
            $out['shared_roles'] = null;
        } elseif (isset($out['shared_roles'])) {
            $out['shared_roles'] = array_values(array_unique($out['shared_roles']));
        }
        if (isset($out['definition'])) {
            $out['definition_version'] = DefinitionValidator::VERSION;
        }

        return $out;
    }

    /** The definition must be valid and on a dataset the saver may run reports on. */
    private function checkDefinition(array $definition, Authenticatable $viewer): void
    {
        $parsed = $this->validator->validate($definition);
        $dataset = $this->registry->get($parsed->dataset);
        if (!(new ReportContext($viewer))->can($dataset->permission())) {
            throw ValidationException::withMessages(['definition.dataset' => ['You cannot run reports on this dataset, so you cannot save one.']]);
        }
    }

    /** @param list<string> $roles */
    private function checkSharing(string $visibility, array $roles): void
    {
        if ($visibility === SavedReport::ROLES && $roles === []) {
            throw ValidationException::withMessages(['shared_roles' => ['Name the roles to share the report with.']]);
        }
        $unknown = array_diff($roles, Role::query()->where('guard_name', 'api')->pluck('name')->all());
        if ($visibility === SavedReport::ROLES && $unknown !== []) {
            throw ValidationException::withMessages(['shared_roles' => ['Unknown role: ' . implode(', ', $unknown) . '.']]);
        }
    }

    private function can(Authenticatable $viewer, string $permission): bool
    {
        return (new ReportContext($viewer))->can($permission);
    }

    /** @return list<string> */
    private function roleNames(Authenticatable $viewer): array
    {
        return method_exists($viewer, 'getRoleNames') ? $viewer->getRoleNames()->values()->all() : [];
    }
}
