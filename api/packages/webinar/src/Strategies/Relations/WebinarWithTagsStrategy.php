<?php

namespace Ulams\Webinar\Strategies\Relations;

use Ulams\Tags\Models\Tag;
use Ulams\Webinar\Strategies\Contracts\RelationStrategyContract;
use Ulams\Webinar\Models\Webinar;

class WebinarWithTagsStrategy implements RelationStrategyContract
{
    private Webinar $webinar;
    private array $data;

    public function __construct(array $params) {
        $this->webinar = $params[0];
        $this->data = $params[1] ?? [];
    }

    public function setRelation(): void
    {
        if (count($this->data['tags']) === 0) {
            $this->webinar->tags()->delete();
        } else {
            $this->webinar->tags()
                ->whereNotIn('title', $this->data['tags'])
                ->delete();
            foreach ($this->data['tags'] as $tag) {
                $this->webinar->tags()->save(new Tag(['title' => $tag]));
            }
        }
    }
}
