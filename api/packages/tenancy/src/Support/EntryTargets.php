<?php

namespace Ulams\Tenancy\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ulams\Tenancy\Models\EntryPoint;

/**
 * Checks, in the tenant's own database, that the group or the course an entry point aims at exists. The
 * platform table cannot hold a foreign key into a tenant database, so the tenant API checks before it asks the
 * platform to store the target. A read only lookup: nothing is written.
 */
final class EntryTargets
{
    /**
     * @param array<string, mixed>|null $target already shaped by {@see EntryPointRules::targetErrors()}
     * @return array<string, list<string>> field errors, empty when the target exists
     */
    public static function missing(string $kind, ?array $target): array
    {
        if ($kind === EntryPoint::DEPARTMENT && Schema::hasTable('groups')
            && !DB::table('groups')->where('id', (int) ($target['group_id'] ?? 0))->exists()) {
            return ['target.group_id' => ['This academy has no such group.']];
        }
        if ($kind === EntryPoint::COURSE && Schema::hasTable('courses')
            && !DB::table('courses')->where('id', (int) ($target['course_id'] ?? 0))->exists()) {
            return ['target.course_id' => ['This academy has no such course.']];
        }

        return [];
    }
}
