<?php

namespace Ulams\TopicTypeLayout\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Ulams\TopicTypeLayout\Database\Factories\LayoutTopicFactory;
use Ulams\TopicTypeLayout\Rules\ValidLayoutDocument;
use Ulams\TopicTypes\Models\TopicContent\AbstractTopicContent;

/**
 * @OA\Schema(
 *      schema="TopicLayout",
 *      required={"document", "markdown_fallback"},
 *      @OA\Property(property="id", type="integer"),
 *      @OA\Property(
 *          property="document",
 *          type="array",
 *          description="Layout document (a JSON string is accepted in multipart requests): a list of {component, props, id?} nodes using only the approved learner layout components (Callout, Steps, ComparisonTable, H5PFrame, LiaScriptLesson, Timeline, FlipCards, CodeBlock, PracticeActivity), validated against the learner layout manifest",
 *          @OA\Items(type="object")
 *      ),
 *      @OA\Property(property="schema_version", type="string", enum={"1"}),
 *      @OA\Property(property="markdown_fallback", type="string", description="Markdown shown by clients that do not render layouts, and when the document cannot be rendered")
 * )
 *
 * Topic type Layout (ADR 0052): a lesson body composed of approved learning components (flip cards,
 * timelines, practice activities, ...), rendered from the learner catalogue by the learner front.
 *
 * @property int $id
 * @property array $document
 * @property string $schema_version
 * @property string $markdown_fallback
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class LayoutTopic extends AbstractTopicContent
{
    use HasFactory;

    public const SCHEMA_VERSION = '1';

    public $table = 'topic_layouts';

    protected $fillable = ['document', 'schema_version', 'markdown_fallback'];

    protected $casts = [
        'id' => 'integer',
        'document' => 'array',
        'schema_version' => 'string',
    ];

    protected $attributes = [
        'schema_version' => self::SCHEMA_VERSION,
    ];

    public static function rules(): array
    {
        return [
            // a list (JSON body) or a JSON string (the admin posts topics as multipart form data)
            'document' => ['required', new ValidLayoutDocument()],
            'schema_version' => ['sometimes', 'in:' . self::SCHEMA_VERSION],
            'markdown_fallback' => ['required', 'string', 'max:100000'],
        ];
    }

    /** The admin sends the document as a JSON string inside multipart form data; it is stored as JSON either way. */
    public function setDocumentAttribute(mixed $value): void
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : $value;
        }
        $this->attributes['document'] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected static function newFactory(): LayoutTopicFactory
    {
        return LayoutTopicFactory::new();
    }

    /** The document is stored in the table, there are no files to move on a course export. */
    public function fixAssetPaths(): array
    {
        return [];
    }

    public function getMorphClass()
    {
        return self::class;
    }
}
