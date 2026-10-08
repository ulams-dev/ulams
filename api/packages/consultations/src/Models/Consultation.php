<?php

namespace EscolaLms\Consultations\Models;

use EscolaLms\Categories\Models\Category;
use EscolaLms\Consultations\Database\Factories\ConsultationFactory;
use EscolaLms\Consultations\Services\Contracts\ConsultationServiceContract;
use EscolaLms\ModelFields\Traits\ModelFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use EscolaLms\Core\Models\User as CoreUser;


/**
 * @OA\Schema(
 *      schema="Consultation",
 *      required={"name", "status", "description", "author_id"},
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="integer",
 *      ),
 *      @OA\Property(
 *          property="name",
 *          description="name",
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="status",
 *          description="status",
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="description",
 *          description="description",
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="duration",
 *          description="duration",
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="author_id",
 *          description="author_id",
 *          type="integer",
 *      ),
 *      @OA\Property(
 *          property="active_to",
 *          description="active_to",
 *          type="datetime",
 *      ),
 *      @OA\Property(
 *          property="active_from",
 *          description="active_from",
 *          type="datetime",
 *      ),
 *      @OA\Property(
 *          property="created_at",
 *          description="created_at",
 *          type="datetime",
 *      ),
 *      @OA\Property(
 *          property="updated_at",
 *          description="updated_at",
 *          type="datetime",
 *      ),
 *      @OA\Property(
 *          property="consultation_user_id",
 *          description="consultation_user_id",
 *          type="integer",
 *      ),
 *      @OA\Property(
 *          property="executed_status",
 *          description="executed_status",
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="image_path",
 *          description="image_path",
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="image_url",
 *          description="image_url",
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="logotype_path",
 *          description="logotype_path",
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="logotype_url",
 *          description="logotype_url",
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="executed_at",
 *          description="executed_at",
 *          type="datetime",
 *      ),
 *      @OA\Property(
 *          property="categories",
 *          description="categories",
 *          type="array",
 *          @OA\Items(
 *             @OA\Property(
 *                 property="name",
 *                 type="string",
 *                 example="Dokumentacja",
 *             ),
 *             @OA\Property(
 *                 property="icon_class",
 *                 type="string",
 *                 example="fa-business-time",
 *             ),
 *             @OA\Property(
 *                 property="is_active",
 *                 type="bool",
 *                 example="true",
 *             ),
 *             @OA\Property(
 *                 property="parent_id",
 *                 type="?integer",
 *                 example="null",
 *             ),
 *          ),
 *      ),
 *      @OA\Property(
 *          property="proposed_terms",
 *          description="proposed_terms",
 *          type="array",
 *          @OA\Items(
 *             @OA\Property(
 *                  property="",
 *                  type="string",
 *                  example="2022-05-20T10:15:20.000000Z",
 *             ),
 *          ),
 *      ),
 *      @OA\Property(
 *          property="busy_terms",
 *          description="busy_terms",
 *          type="array",
 *          @OA\Items(
 *             @OA\Property(
 *                  property="",
 *                  type="string",
 *                  example="2022-05-20T10:15:20.000000Z",
 *             ),
 *          ),
 *      ),
 *      @OA\Property(
 *          property="max_session_students",
 *          description="max_session_students",
 *          type="integer",
 *      ),
 *     @OA\Property(
 *           property="analyze_enabled",
 *           description="analyze_enabled",
 *           type="bool",
 *       ),
 * )
 *
 * @property int $author_id
 * @property int $max_session_students
 * @property string $name
 * @property string $logotype_path
 * @property ?string $image_path
 */
class Consultation extends Model
{
    use HasFactory;
    use ModelFields;

    protected $fillable = [
        'name',
        'status',
        'duration',
        'description',
        'short_desc',
        'image_path',
        'logotype_path',
        'author_id',
        'active_from',
        'active_to',
        'max_session_students',
        'analyze_enabled',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'consultation_user');
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'consultation_teachers', 'consultation_id', 'teacher_id');
    }

    public function proposedTerms(): HasMany
    {
        return $this->hasMany(ConsultationProposedTerm::class, 'consultation_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(ConsultationUserPivot::class);
    }

    public function getImageUrlAttribute(): string
    {
        if ($this->attributes['image_path'] ?? null) {
            $path = trim(trim($this->attributes['image_path'], '/'));
            if ($path) {
                $imagePath = Storage::url($path);
                return preg_match('/^(http|https):.*$/', $imagePath, $oa) ?
                    $imagePath :
                    url($imagePath);
            }
        }
        return '';
    }

    public function getLogotypeUrlAttribute(): string
    {
        if ($this->attributes['logotype_path'] ?? null) {
            $path = trim(trim($this->attributes['logotype_path'], '/'));
            if ($path) {
                $logotype = Storage::url(trim($this->attributes['logotype_path'], '/'));
                return preg_match('/^(http|https):.*$/', $logotype, $oa) ?
                    $logotype :
                    url($logotype);
            }
        }
        return '';
    }

    public function attachToConsultationPivot(array $data, ?array $termData = null): void
    {
        $consultationServiceContract = app(ConsultationServiceContract::class);
        $consultationServiceContract->attachToUser($data, $termData);
    }

    public function getDuration(): string
    {
        return $this->duration ?? '0';
    }

    protected static function newFactory(): ConsultationFactory
    {
        return ConsultationFactory::new();
    }
}
