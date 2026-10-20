<?php

namespace Ulams\Courses\Models\TopicContent;

use Ulams\Core\Models\Concerns\HasMorphAlias;
use Ulams\Courses\Models\Contracts\TopicContentContract;
use Ulams\Courses\Models\Topic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

abstract class AbstractTopicContent extends Model implements TopicContentContract
{
    use HasMorphAlias;

    protected $fillable = [
        'value',
    ];

    protected $casts = [
        'id' => 'integer',
        'value' => 'string',
    ];

    public static function rules(): array
    {
        return [
            'value' => ['required'],
        ];
    }

    public function topic(): MorphOne
    {
        return $this->morphOne(Topic::class, 'topicable');
    }

    public function fixAssetPaths(): array
    {
        return [];
    }
}
