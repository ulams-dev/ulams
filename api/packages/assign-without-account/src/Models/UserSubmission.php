<?php

namespace EscolaLms\AssignWithoutAccount\Models;

use EscolaLms\AssignWithoutAccount\Database\Factories\UserSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @OA\Schema(
 *      schema="UserSubmission",
 *      @OA\Property(
 *          property="id",
 *          description="user submission id",
 *          type="integer",
 *      ),
 *      @OA\Property(
 *          property="email",
 *          description="user email",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="status",
 *          description="status",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="morphable_id",
 *          description="morph id",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="morphable_type",
 *          description="morph type",
 *          type="string"
 *      ),
 * )
 */
class UserSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'status',
        'morphable_type',
        'morphable_id'
    ];

    protected $casts = [
        'email' => 'string',
        'status' => 'string',
        'morphable_type' => 'string',
        'morphable_id' => 'integer'
    ];

    public function morphable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): UserSubmissionFactory
    {
        return UserSubmissionFactory::new();
    }
}
