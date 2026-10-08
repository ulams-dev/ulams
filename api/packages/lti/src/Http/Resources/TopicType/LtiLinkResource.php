<?php

namespace Ulams\Lti\Http\Resources\TopicType;

use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\Lti\Models\LtiLink;
use Ulams\TopicTypes\Http\Resources\TopicType\Contacts\TopicTypeResourceContract;

/**
 * Admin, client and export representation of an LTI link. Learners never get the tool's keys or
 * URLs beyond the target link; the launch itself goes through POST /api/lti/launches/{topic}.
 *
 * @mixin LtiLink
 */
class LtiLinkResource extends JsonResource implements TopicTypeResourceContract
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'lti_tool_id' => $this->lti_tool_id,
            'tool_name' => $this->tool?->name,
            'url' => $this->url,
            'custom' => $this->custom,
            'presentation' => $this->presentation,
            'score_maximum' => $this->score_maximum,
        ];
    }
}
