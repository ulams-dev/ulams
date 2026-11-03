<?php

namespace Ulams\CourseBuilder\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * The typed, cited and author-approved reading of a style guide (ADR 0196).
 *
 * @property string $id
 * @property string $scope_type tenant | entry_point | brief
 * @property string|null $scope_id
 * @property string $name
 * @property string|null $source_id the style guide source
 * @property string|null $session_id the holder session of that source
 * @property array $profile see resources/schemas/style-profile/v1.json
 * @property array|null $warnings what the sanitiser dropped, shown to the author
 * @property string $status suggested | approved | superseded
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 */
class StyleProfile extends Model
{
    use HasUlids;

    public const SUGGESTED = 'suggested';
    public const APPROVED = 'approved';
    public const SUPERSEDED = 'superseded';

    public const TENANT = 'tenant';

    public const SUBJECT_TYPE = 'course_builder_style_profile';

    protected $table = 'course_builder_style_profiles';

    protected $guarded = [];

    protected $casts = ['profile' => 'array', 'warnings' => 'array', 'approved_at' => 'datetime', 'created_by' => 'integer', 'approved_by' => 'integer'];

    /** The approved profile of a scope, null when there is none. */
    public static function current(string $scopeType = self::TENANT, ?string $scopeId = null): ?self
    {
        return self::query()->where('scope_type', $scopeType)->where('scope_id', $scopeId)->where('status', self::APPROVED)->latest('approved_at')->first();
    }
}
