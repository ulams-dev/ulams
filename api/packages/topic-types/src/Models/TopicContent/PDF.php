<?php

namespace EscolaLms\TopicTypes\Models\TopicContent;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

/**
 * @OA\Schema(
 *      schema="TopicPDF",
 *      required={"value"},
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          @OA\Schema(
 *             type="integer",
 *         )
 *      ),
 *      @OA\Property(
 *          property="value",
 *          description="value",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="length",
 *          description="length",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="page_count",
 *          description="page_count",
 *          type="integer"
 *      )
 * )
 *
 * @property int $length
 * @property int $page_count
 */
class PDF extends AbstractTopicFileContent
{
    use HasFactory;

    public $table = 'topic_pdfs';

    public static function rules(): array
    {
        return [
            'value' => ['required', 'mimes:pdf'],
        ];
    }

    protected static function newFactory()
    {
        return \EscolaLms\TopicTypes\Database\Factories\TopicContent\PdfFactory::new();
    }

    public function getStoragePathFinalSegment(): string
    {
        return 'pdf';
    }

    protected function processUploadedFiles(): void
    {
        try {
            $pdf = (new Parser())->parseFile(Storage::path($this->value));
            $this->length = strlen($pdf->getText());
            $this->page_count = count($pdf->getPages());
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function processMetadataInfo(): void
    {
        $this->processUploadedFiles();
    }

    public function getMorphClass()
    {
        return self::class;
    }
}
