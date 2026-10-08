<?php

namespace EscolaLms\Categories\Services;

use EscolaLms\Categories\Dtos\CategoryDto;
use EscolaLms\Categories\Dtos\CategorySortDto;
use EscolaLms\Categories\Enums\ConstantEnum;
use EscolaLms\Categories\Exceptions\CategoryIsUsed;
use EscolaLms\Categories\Models\Category;
use EscolaLms\Categories\Repositories\Contracts\CategoriesRepositoryContract;
use EscolaLms\Categories\Services\Contracts\CategoryServiceContracts;
use EscolaLms\Core\Dtos\PaginationDto;
use EscolaLms\Core\Dtos\PeriodDto;
use EscolaLms\Files\Helpers\FileHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryService implements CategoryServiceContracts
{
    private CategoriesRepositoryContract $categoryRepository;

    /**
     * CategoryService constructor.
     * @param CategoriesRepositoryContract $categoryRepository
     */
    public function __construct(CategoriesRepositoryContract $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function slugify(string $name): string
    {
        $slug = Str::slug($name, '-');

        $total = Category::where('slug', 'like', $slug . '%')->count();
        return ($total > 0) ? "{$slug}-{$total}" : $slug;
    }

    public function getList(?string $search = null)
    {
        $paginate_count = 10;

        if ($search) {
            return Category::where('name', 'LIKE', '%' . $search . '%')->paginate($paginate_count);
        }

        return Category::paginate($paginate_count);
    }

    public function find(?int $id = null)
    {
        if ($id) {
            return Category::find($id);
        }

        return Controller::getColumnTable('categories');
    }

    public function store(CategoryDto $categoryDto): Category
    {
        return DB::transaction(function () use($categoryDto) {
            $category = $this->categoryRepository->create($categoryDto->toArray());
            $category->slug = $this->slugify($category->name);

            if (!is_null($categoryDto->getIcon())) {
                $category->icon = $this->saveIcon($categoryDto->getIcon(), $category->getKey());
            }

            $category->save();

            return $category;
        });
    }

    public function update(int $id, CategoryDto $categoryDto): Category
    {
        return DB::transaction(function () use($categoryDto, $id) {
            $category = $this->categoryRepository->update($categoryDto->toArray(), $id);
            $category->slug = $this->slugify($category->name);

            if (!is_null($categoryDto->getIcon())) {
                $category->icon = $this->saveIcon($categoryDto->getIcon(), $category->getKey());
            }

            if ($categoryDto->getIconPath() !== false) {
                $category->icon = $categoryDto->getIconPath();
            }

            $category->save();

            return $category;
        });
    }

    /**
     * @throws CategoryIsUsed
     */
    public function delete(int $id): void
    {
        $category = $this->categoryRepository->get($id);

        if ($category->children()->count() > 0) {
            throw new CategoryIsUsed(__('The category has categories'));
        }

        if (class_exists(\EscolaLms\Courses\Models\Course::class) && $category->courses()->count() > 0) {
            throw new CategoryIsUsed(__('The category is used in courses'));
        }

        $this->categoryRepository->delete($id);
    }

    public function getPopular(PaginationDto $pagination, PeriodDto $period): Collection
    {
        return $this->categoryRepository->getByPopularity($pagination, $period->from(), $period->to());
    }

    public function allCategoriesAndChildrenIds(array $categoryIds): array
    {
        $result = [];
        foreach ($categoryIds as $categoryId) {
            $categories = Category::where('id', $categoryId)->with(['children'])->get();
            $flat = $this->flatten($categories, 'children');
            $result = array_merge($result, array_map(fn ($cat) => $cat->id, $flat));
        }
        return $result;
    }

    public function sort(CategorySortDto $dto): void
    {
        foreach ($dto->getOrders() as $oder)
            $this->categoryRepository->update(['order' => $oder['order']], $oder['id']);
    }

    private function saveIcon($icon, $id): string
    {
        return FileHelper::getFilePath($icon, ConstantEnum::DIRECTORY . "/{$id}/icons");
    }

    private function flatten($input, string $key): array
    {
        $output = [];
        foreach ($input as $object) {
            $children = $object->$key ?? [];
            $object->$key = [];
            $output[] = $object;
            $children = $this->flatten($children, $key);
            foreach ($children as $child) {
                $output[] = $child;
            }
        }
        return $output;
    }
}
