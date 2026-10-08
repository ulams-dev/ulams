<?php

namespace EscolaLms\Categories\Repositories;

use Carbon\Carbon;
use EscolaLms\Categories\Dtos\CategoryCriteriaFilterDto;
use EscolaLms\Categories\Models\Category;
use EscolaLms\Categories\Repositories\Contracts\CategoriesRepositoryContract;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Dtos\PaginationDto;
use EscolaLms\Core\Repositories\BaseRepository;
use EscolaLms\Core\Repositories\Traits\Activationable;
use EscolaLms\Courses\Enum\CourseStatusEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CategoriesRepositoryRepository
 * @package App\Repositories
 * @version December 7, 2020, 11:22 am UTC
 */
class CategoriesRepository extends BaseRepository implements CategoriesRepositoryContract
{
    use Activationable;

    /**
     * @var array
     */
    protected $fieldSearchable = [
        'name',
        'slug',
        'icon_class',
        'is_active',
        'parent_id'
    ];

    /**
     * Return searchable fields
     *
     * @return array
     */
    public function getFieldsSearchable()
    {
        return $this->fieldSearchable;
    }

    /**
     * Configure the Model
     **/
    public function model()
    {
        return Category::class;
    }

    public function allRoots(array $search = [], ?int $skip = null, ?int $limit = null): Collection
    {
        $query = $this->allQuery($search, $skip, $limit)
            ->with(['children', 'children.parent'])
            ->whereNull('parent_id');

        $query = $this->withCoursesCount($query);

        return $query->get();
    }

    public function getByPopularity(PaginationDto $pagination, ?Carbon $from = null, ?Carbon $to = null): \Illuminate\Support\Collection
    {
        $query = $this->model->newQuery()
            ->withCount(['users' => function ($q) use ($from, $to) {
                if (!is_null($from)) {
                    $q->where('category_user.created_at', '>=', $from);
                }

                if (!is_null($to)) {
                    $q->where('category_user.created_at', '<=', $to);
                }
            }]);

        if (!is_null($from)) {
            $query->where('categories.created_at', '>=', $from);
        }

        if (!is_null($to)) {
            $query->where('categories.created_at', '<=', $to);
        }

        $query->orderBy('users_count', 'DESC');

        $this->applyPaginationDto($query, $pagination);

        return $query->get();
    }

    public function get(int $id): Category
    {
        $query = $this->model->newQuery();

        return $query->findOrFail($id);
    }

    public function listAll(CategoryCriteriaFilterDto $criteriaDto, OrderDto $dto, array $columns = ['*'], ?int $perPage = 15, ?bool $isActive = null): LengthAwarePaginator
    {
        $query = $this->queryWithAppliedCriteria($criteriaDto->toArray())->with('parent');

        if (!is_null($isActive)) {
            $query->where('is_active', $isActive);
        }

        $query = $this->withCoursesCount($query);

        if ($dto->getOrderBy()) {
            $query->orderBy($dto->getOrderBy(), $dto->getOrder() ?? 'asc');
        }

        return $query->paginate($perPage, $columns);
    }

    private function withCoursesCount(Builder $query): Builder
    {
        if (class_exists(\EscolaLms\Courses\Models\Course::class)) {
            $query->withCount([
                'courses as published_courses' => function (Builder $query) {
                    $query->where('status','=', CourseStatusEnum::PUBLISHED);
                }
            ]);
        }

        return $query;
    }
}
