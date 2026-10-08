<?php

namespace EscolaLms\Templates\Repository;

use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\Templates\Helpers\Models;
use EscolaLms\Templates\Models\Template;
use EscolaLms\Templates\Models\TemplateSection;
use EscolaLms\Templates\Repository\Contracts\TemplateRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class TemplateRepository extends BaseRepository implements TemplateRepositoryContract
{
    public function model()
    {
        return Template::class;
    }

    public function getFieldsSearchable()
    {
        return [
            'name',
            'channel',
            'event',
            'default',
        ];
    }

    public function searchAndPaginate(array $search = [], ?int $perPage = null, string $orderDirection = 'asc', string $orderColumn = 'id'): LengthAwarePaginator
    {
        return $this->allQuery($search)->orderBy($orderColumn, $orderDirection)->with(['sections', 'templatables'])->paginate($perPage);
    }

    public function deleteTemplate(int $id): bool
    {
        /** @var Template $template */
        $template = $this->find($id);

        if (!$template) {
            return false;
        }

        $template->sections()->delete();
        return $template->delete();
    }

    public function findTemplateDefault(string $event, string $channel): ?Template
    {
        return $this->allQuery([
            'event' => $event,
            'channel' => $channel,
            'default' => true,
        ])->first();
    }

    public function findTemplateAssigned(string $event, string $channel, string $assigned_class, int $assigned_value): ?Template
    {
        if (is_a($assigned_class, Model::class, true)) {
            return $this->allQuery([
                'event' => $event,
                'channel' => $channel,
            ])->whereHas('templatables', fn (Builder $query) => $query->where('templatable_id', $assigned_value)->where('templatable_type', Models::getMorphClassFromModelClass($assigned_class)))
                ->first();
        }
        return null;
    }

    public function createWithSections(array $attributes, array $sections): Template
    {
        /** @var Template $template */
        $template = $this->create($attributes);
        foreach ($sections as $section => $content) {
            $template->sections()->save(new TemplateSection([
                'key' => $section,
                'content' => $content,
            ]));
        }
        return $template;
    }

    public function updateWithSections(array $attributes, array $sections, int $id): Template
    {
        /** @var Template $template */
        $template = $this->update($attributes, $id);
        foreach ($sections as $section => $content) {
            $section = TemplateSection::updateOrCreate(['template_id' => $template->getKey(), 'key' => $section], ['content' => $content]);
        }
        return $template;
    }

    public function findAllTemplatesAssigned(string $assignable_class, int $assignable_id): Collection
    {
        if (is_a($assignable_class, Model::class, true)) {
            return $this->allQuery()->whereHas('templatables', fn (Builder $query) => $query->where('templatable_id', $assignable_id)->where('templatable_type', Models::getMorphClassFromModelClass($assignable_class)))->get();
        }
        return Collection::empty();
    }
}
