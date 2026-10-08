<?php

namespace Ulams\TemplatesPdf\Models;

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
 * )
 */
class FabricPDF extends Model
{
    use HasFactory;

    protected $table = 'fabric_pdfs';

    protected $casts = [
        'id' => 'integer',
        'content' => 'array',
        'vars' => 'array',
    ];

    protected $guarded = [
        'id'
    ];

    protected static function newFactory()
    {
        return FabricPdfFactory::new();
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
