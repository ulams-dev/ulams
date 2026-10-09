<?php

namespace Ulams\Interactive\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Ulams\Interactive\Enums\CompletionRule;
use Ulams\Interactive\Enums\DisplayMode;
use Ulams\Interactive\Observers\InteractiveTopicObserver;
use Ulams\TopicTypes\Models\TopicContent\AbstractTopicContent;

/**
 * @OA\Schema(
 *      schema="TopicInteractive",
 *      required={"value"},
 *      @OA\Property(property="id", type="integer"),
 *      @OA\Property(property="value", type="integer", description="Interactive package id"),
 *      @OA\Property(property="version", type="integer", nullable=true, description="Pinned package version; set to the current one when omitted"),
 *      @OA\Property(property="follow_latest", type="boolean", description="Play the package's current version instead of a pinned one"),
 *      @OA\Property(property="start_step", type="string", nullable=true),
 *      @OA\Property(property="end_step", type="string", nullable=true),
 *      @OA\Property(property="completion_rule", type="string", enum={"on_open","on_range_end","on_complete","on_score"}),
 *      @OA\Property(property="pass_score", type="integer", nullable=true, description="Percentage, for on_score"),
 *      @OA\Property(property="display", type="string", enum={"inline","background"}),
 *      @OA\Property(property="height", type="integer"),
 *      @OA\Property(property="text", type="string", nullable=true, description="Markdown shown beside the frame or over the background")
 * )
 *
 * Topic type: plays an uploaded interactive package between two steps (ADR 0086).
 *
 * @property int $id
 * @property int $value
 * @property ?int $version
 * @property bool $follow_latest
 * @property ?string $start_step
 * @property ?string $end_step
 * @property string $completion_rule
 * @property ?int $pass_score
 * @property string $display
 * @property int $height
 * @property ?string $text
 * @property-read InteractivePackage|null $package
 */
class InteractiveTopic extends AbstractTopicContent
{
    /** Folder of the package files inside topic/<id>/ of a course export. */
    public const EXPORT_FOLDER = 'interactive';

    public $table = 'topic_interactives';

    protected $fillable = ['value', 'version', 'follow_latest', 'start_step', 'end_step', 'completion_rule', 'pass_score', 'display', 'height', 'text'];

    protected $casts = [
        'value' => 'integer',
        'version' => 'integer',
        'follow_latest' => 'boolean',
        'pass_score' => 'integer',
        'height' => 'integer',
    ];

    protected $attributes = [
        'follow_latest' => false,
        'completion_rule' => 'on_range_end',
        'display' => 'inline',
        'height' => 640,
    ];

    /**
     * The observer is attached when the model first boots, not in the provider: booting an
     * AbstractTopicContent asks the guard for the current user, which needs the Passport keys, and the
     * provider also runs for `package:discover` during `composer install`, where there are none.
     */
    protected static function booted()
    {
        parent::booted();
        static::observe(InteractiveTopicObserver::class);
    }

    public static function rules(): array
    {
        return [
            'value' => ['required', 'integer', 'exists:interactive_packages,id'],
            'version' => ['nullable', 'integer', 'min:1'],
            'follow_latest' => ['sometimes', 'boolean'],
            'start_step' => ['nullable', 'string', 'max:64'],
            'end_step' => ['nullable', 'string', 'max:64'],
            'completion_rule' => ['sometimes', 'in:' . implode(',', array_column(CompletionRule::cases(), 'value'))],
            'pass_score' => ['nullable', 'integer', 'between:0,100', 'required_if:completion_rule,on_score'],
            'display' => ['sometimes', 'in:' . implode(',', array_column(DisplayMode::cases(), 'value'))],
            'height' => ['sometimes', 'integer', 'between:240,2000'],
            'text' => ['nullable', 'string', 'max:20000'],
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(InteractivePackage::class, 'value');
    }

    /** The version this topic plays: the pinned one, or the package's current version. */
    public function resolveVersion(): ?InteractivePackageVersion
    {
        $package = $this->package;

        return $package?->version($this->follow_latest ? $package->current_version : ($this->version ?? $package->current_version));
    }

    /**
     * Course export: writes the played version's files to course/<course>/topic/<topic>/interactive/,
     * which the export copies into the archive. The package and its versions stay where they are.
     */
    public function fixAssetPaths(): array
    {
        $topic = $this->topic;
        $course = $topic?->lesson?->course;
        $version = $this->resolveVersion();
        if ($course === null || $version === null) {
            return [];
        }

        $target = sprintf('course/%d/topic/%d/%s', $course->getKey(), $topic->getKey(), self::EXPORT_FOLDER);
        $source = Storage::disk(config('ulams_interactive.disk') ?: config('filesystems.default'));
        Storage::deleteDirectory($target);
        $directory = $version->package->directory($version->version);
        foreach (array_keys($version->files ?? []) as $relative) {
            if (!is_string($relative) || str_contains($relative, '..') || !$source->exists($directory . '/' . $relative)) {
                continue;
            }
            Storage::put($target . '/' . ltrim($relative, '/'), (string) $source->get($directory . '/' . $relative));
        }

        return [];
    }

    public function getMorphClass()
    {
        return self::class;
    }
}
