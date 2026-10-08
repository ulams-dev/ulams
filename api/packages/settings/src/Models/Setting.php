<?php

namespace EscolaLms\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use EscolaLms\Settings\Casts\Setting as SettingCast;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *      schema="Setting",
 *      required={"title"},
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="integer",
 *      ),
 *      @OA\Property(
 *          property="key",
 *          description="key",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="group",
 *          description="group",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="value",
 *          description="value",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="type",
 *          description="type",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="public",
 *          description="public",
 *          type="boolean"
 *      ),
 *      @OA\Property(
 *          property="enumerable",
 *          description="enumerable",
 *          type="boolean"
 *      ),
 *      @OA\Property(
 *          property="sort",
 *          description="sort",
 *          type="integer",
 *      ),
 * )
 *
 * @property int $id
 * @property string $type
 * @property string $value
 * @property string $key
 * @property string $group
 * @property boolean $public
 * @property boolean $enumerable
 * @property int $sort
 */

class Setting extends Model
{
    public $table = 'settings';

    public $fillable = [
        'key',
        'group',
        'value',
        'public',
        'enumerable',
        'sort',
        'type',
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'key' => 'string',
        'group' => 'string',
        'public' => 'boolean',
        'enumerable' => 'boolean',
        'sort' => 'integer',
        'type' => 'string',
        'value' => 'string',
    ];

    protected $appends = [
        'data'
    ];

    public function getDataAttribute()
    {
        switch($this->type) {
            case "config":
                return config($this->value);
            case "json":
            case "array":
                return json_decode($this->value);
            case "image":
            case "file":
                if (Str::startsWith($this->value, 'http'))
                    return $this->value;
                $path = trim(trim($this->value, '/'));
                return Storage::url($path);
            case "boolean":
                return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
            case "number":
                return floatval($this->value);
            default:
                return $this->value;
        }
    }
}
