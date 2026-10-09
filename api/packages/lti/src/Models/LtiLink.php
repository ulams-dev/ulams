<?php

namespace Ulams\Lti\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ulams\TopicTypes\Models\TopicContent\AbstractTopicContent;

/**
 * @OA\Schema(
 *      schema="TopicLtiLink",
 *      required={"lti_tool_id"},
 *      @OA\Property(property="id", type="integer"),
 *      @OA\Property(property="lti_tool_id", type="integer", description="registered LTI tool"),
 *      @OA\Property(property="url", type="string", description="target link URI; the tool's launch URL when empty"),
 *      @OA\Property(property="custom", type="object", description="custom parameters sent with the launch"),
 *      @OA\Property(property="presentation", type="string", enum={"iframe", "window"}),
 *      @OA\Property(property="score_maximum", type="number")
 * )
 *
 * Topic type: a link to an external LTI 1.3 tool, launched by the platform side.
 *
 * @property int $id
 * @property int $lti_tool_id
 * @property ?string $url
 * @property ?array $custom
 * @property string $presentation
 * @property float $score_maximum
 * @property-read LtiTool $tool
 */
class LtiLink extends AbstractTopicContent
{
    public $table = 'topic_lti_links';

    protected $fillable = ['lti_tool_id', 'url', 'custom', 'presentation', 'score_maximum'];

    protected $casts = [
        'custom' => 'array',
        'score_maximum' => 'float',
    ];

    protected $attributes = [
        'presentation' => 'iframe',
        'score_maximum' => 100,
    ];

    public static function rules(): array
    {
        return [
            'lti_tool_id' => ['required', 'integer', 'exists:lti_tools,id'],
            'url' => ['nullable', 'url', 'max:2048'],
            'custom' => ['nullable', 'array'],
            'custom.*' => ['string', 'max:1024'],
            'presentation' => ['nullable', 'in:iframe,window'],
            'score_maximum' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(LtiTool::class, 'lti_tool_id');
    }

    /**
     * Code that treats every topic content as having a `value` (exports, notifications) gets
     * the target link.
     */
    public function getValueAttribute(): ?string
    {
        return $this->url ?: $this->tool?->launch_url;
    }

    /** Nothing is stored in the bucket for a link. */
    public function fixAssetPaths(): array
    {
        return [];
    }

    public function getMorphClass()
    {
        return self::class;
    }
}
