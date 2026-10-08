<?php


namespace EscolaLms\Tags\Repository;


use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\Courses\Enum\CourseStatusEnum;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Tags\Models\Tag;
use EscolaLms\Tags\Repository\Contracts\TagRepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TagRepository extends BaseRepository implements TagRepositoryContract
{

    /**
     * @var array
     */
    protected array $fieldSearchable = [
        'title'
    ];

    /**
     * Return searchable fields
     *
     * @return array
     */
    public function getFieldsSearchable() : array
    {
        return $this->fieldSearchable;
    }

    /**
     * Configure the Model
     * @return string
     */
    public function model() : string
    {
        return Tag::class;
    }

    /**
     * @param array $tagData
     * @return Tag
     */
    public function insert(array $tagData) : Tag
    {
        return $this->model->create($tagData);
    }

    /**
     * @param string $column
     * @param array $payload
     * @return bool|null
     */
    public function deleteWhereIn(string $column, array $payload): ?bool
    {
        return $this->model->newQuery()->whereIn($column, $payload)->delete();
    }

    /**
     * Build a query for retrieving unique titles.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function unique(): Collection
    {
        return $this->model->select('title')->distinct('title')->get();
    }

    public function uniqueTagsFromActiveCourses(): ?Collection
    {
        return $this->model->select('title')
            ->whereHasMorph('morphable', Course::class, function(Builder $query) {
                $query->where('status', '=', CourseStatusEnum::PUBLISHED);
            })
            ->distinct('title')
            ->get();
    }
}
