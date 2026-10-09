<?php

namespace Ulams\Interactive\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ulams\Interactive\Models\InteractivePackage;

/**
 * @mixin InteractivePackage
 */
class InteractivePackageResource extends JsonResource
{
    public function __construct($resource, private readonly bool $withManifest = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $current = $this->resource->current;
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'current_version' => $this->current_version,
            'versions_count' => $this->versions_count ?? $this->versions()->count(),
            'topics_count' => $this->topics_count ?? $this->topics()->count(),
            'licence' => $current?->licence,
            'author_id' => $this->author_id,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
        if ($this->withManifest) {
            $data['manifest'] = $current?->manifest;
            $data['network'] = $current?->manifest['network'] ?? [];
            $data['network_allowed'] = (bool) config('ulams_interactive.allow_network');
            $data['files'] = $current ? array_map(fn ($f) => $f['size'], $current->files) : [];
        }

        return $data;
    }
}
