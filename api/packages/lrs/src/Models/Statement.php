<?php

namespace Ulams\Lrs\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ulams\Lrs\Database\Factories\StatementFactory;

/**
 * A stored xAPI statement. `data` holds the statement exactly as returned by the API.
 *
 * @property int $id
 * @property string $uuid
 * @property object $data
 * @property bool $voided
 * @property bool $pending
 * @property int $validation
 * @property int|null $owner_id
 * @property int|null $entity_id
 * @property int|null $client_id
 * @property int|null $access_id
 */
class Statement extends Model
{
    use HasFactory;

    /** `validation` value of a statement that passed validation. */
    public const VALIDATION_PASSED = 1;

    protected $table = 'trax_xapi_statements';

    protected $casts = [
        'data' => 'object',
        'voided' => 'boolean',
        'pending' => 'boolean',
    ];

    protected $attributes = [
        'voided' => false,
        'pending' => false,
    ];

    protected $fillable = ['uuid', 'data', 'voided', 'pending', 'validation', 'owner_id', 'entity_id', 'client_id', 'access_id'];

    public function access(): BelongsTo
    {
        return $this->belongsTo(Access::class);
    }

    protected static function newFactory(): StatementFactory
    {
        return StatementFactory::new();
    }
}
