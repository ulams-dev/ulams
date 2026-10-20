<?php

namespace Ulams\TemplatesPdf\Models;

use Ulams\Core\Models\Concerns\HasMorphTypeColumns;
use Ulams\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Ulams\TemplatesPdf\Database\Factories\FabricPdfFactory;
use Ulams\Templates\Services\Contracts\TemplateServiceContract;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Ulams\Templates\Models\Template;
use Ulams\TemplatesPdf\Events\PdfCreated;

/**
 * @OA\Schema(
 *      schema="FabricPDF",
 *      @OA\Property(
 *          property="id",
 *          description="template id",
 *          type="integer",
 *      ),
 *      @OA\Property(
 *          property="title",
 *          description="title",
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="content",
 *          description="pdfme template (JSON) the PDF is rendered from",
 *          type="object",
 *      ),
 *      @OA\Property(
 *          property="path",
 *          description="path to rendered PDF binary file",
 *          type="string",
 *      ),
 *       @OA\Property(
 *           property="assignable_type",
 *           type="string",
 *       ),
 *      @OA\Property(
 *           property="assignable_id",
 *           type="integer",
 *       ),
 *      @OA\Property(
 *           property="status",
 *           description="valid, expired or revoked",
 *           type="string",
 *      ),
 *      @OA\Property(
 *           property="expires_at",
 *           type="string",
 *           format="date-time",
 *           nullable=true,
 *      ),
 * )
 */
class FabricPDF extends Model
{
    use HasFactory;
    use HasMorphTypeColumns;

    /** @var array<int, string> polymorphic type columns, stored as morph aliases (see MorphMap) */
    protected $morphTypeColumns = ['assignable_type'];

    protected $table = 'fabric_pdfs';

    protected $casts = [
        'id' => 'integer',
        'content' => 'array',
        'vars' => 'array',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $guarded = [
        'id'
    ];

    protected static function newFactory()
    {
        return FabricPdfFactory::new();
    }

    public const STATUS_VALID = 'valid';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    /** Revocation wins over expiry: a revoked certificate is never "expired". */
    public function status(): string
    {
        if ($this->revoked_at !== null) {
            return self::STATUS_REVOKED;
        }

        return $this->expires_at !== null && $this->expires_at->isPast() ? self::STATUS_EXPIRED : self::STATUS_VALID;
    }

    public function isValid(): bool
    {
        return $this->status() === self::STATUS_VALID;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    protected static function booted()
    {
        self::created(function (FabricPDF $pdf) {
            event(new PdfCreated($pdf->user, $pdf));
        });
    }
}
