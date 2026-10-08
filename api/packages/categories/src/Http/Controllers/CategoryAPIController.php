<?php

namespace EscolaLms\Categories\Http\Controllers;

use EscolaLms\Categories\Dtos\CategoryCriteriaFilterDto;
use EscolaLms\Categories\Dtos\CategoryDto;
use EscolaLms\Categories\Enums\CategoriesPermissionsEnum;
use EscolaLms\Categories\Http\Controllers\Swagger\CategorySwagger;
use EscolaLms\Categories\Http\Requests\CategoryCreateRequest;
use EscolaLms\Categories\Http\Requests\CategoryDeleteRequest;
use EscolaLms\Categories\Http\Requests\CategoryListRequest;
use EscolaLms\Categories\Http\Requests\CategoryReadRequest;
use EscolaLms\Categories\Http\Requests\CategorySortRequest;
use EscolaLms\Categories\Http\Requests\CategoryUpdateRequest;
use EscolaLms\Categories\Http\Resources\CategoryResource;
use EscolaLms\Categories\Http\Resources\CategoryTreeAdminResource;
use EscolaLms\Categories\Http\Resources\CategoryTreeResource;
use EscolaLms\Categories\Repositories\Contracts\CategoriesRepositoryContract;
use EscolaLms\Categories\Services\Contracts\CategoryServiceContracts;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use Illuminate\Http\JsonResponse;


class CategoryAPIController extends EscolaLmsBaseController implements CategorySwagger
{
    private CategoriesRepositoryContract $categoryRepository;
    private CategoryServiceContracts $categoryService;

    public function __construct(CategoriesRepositoryContract $categoryRepository, CategoryServiceContracts $categoryService)
    {
        $this->categoryRepository = $categoryRepository;
        $this->categoryService = $categoryService;
    }

    /**
     * @param CategoryListRequest $request
     * @return JsonResponse
     */
    public function index(CategoryListRequest $request): JsonResponse
    {
        $user = $request->user();
        $criteriaDto = CategoryCriteriaFilterDto::instantiateFromRequest($request);
        $orderDto = OrderDto::instantiateFromRequest($request);

        $isActive = $request->has('is_active') ? $request->boolean('is_active') : null;

        if (!isset($user) || !$user->can(CategoriesPermissionsEnum::CATEGORY_LIST)) {
            $isActive = true;
        }

        $categories = $this->categoryRepository->listAll(
            $criteriaDto,
            $orderDto,
            $request->get('columns', ['*']),
            $request->get('per_page', 15),
            $isActive
        );

        return $this->sendResponseForResource(CategoryResource::collection($categories), "Categories retrieved successfully");
    }

    /**
     * @param CategoryListRequest $request
     * @return JsonResponse
     */
    public function tree(CategoryListRequest $request): JsonResponse
    {
        $user = $request->user();
        $withActive = isset($user) && $user->can(CategoriesPermissionsEnum::CATEGORY_LIST);

        $search = $request->except(['skip', 'limit']);
        if (!$withActive) {
            $search['is_active'] = true;
        }

        $categories = $this->categoryRepository->allRoots(
            $search,
            $request->get('skip'),
            $request->get('limit')
        );

        $response = (!$withActive)
            ? CategoryTreeResource::collection($categories)
            : CategoryTreeAdminResource::collection($categories);

        return $this->sendResponseForResource($response, "Categories tree retrieved successfully");
    }

    /**
     * @param int $id
     * @param CategoryReadRequest $categoryReadRequest
     * @return JsonResponse
     */
    public function show(int $id, CategoryReadRequest $categoryReadRequest): JsonResponse
    {
        $category = $this->categoryRepository->find($id);

        return $this->sendResponseForResource(CategoryResource::make($category));
    }

    /**
     * @param CategoryDeleteRequest $categoryDeleteRequest
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id, CategoryDeleteRequest $categoryDeleteRequest): JsonResponse
    {
        $this->categoryService->delete($id);

        return $this->sendSuccess(__('Category deleted successfully.'));
    }

    /**
     * @param int $id
     * @param CategoryUpdateRequest $request
     * @return JsonResponse
     */
    public function update(int $id, CategoryUpdateRequest $request): JsonResponse
    {
        $categoryDto = new CategoryDto($request->all());
        $category = $this->categoryService->update($id, $categoryDto);

        return $this->sendResponseForResource(CategoryResource::make($category), __('Category updated successfully'));
    }

    /**
     * @param CategoryCreateRequest $categoryCreateRequest
     * @return JsonResponse
     */
    public function create(CategoryCreateRequest $categoryCreateRequest): JsonResponse
    {
        $categoryDto = new CategoryDto($categoryCreateRequest->all());
        $category = $this->categoryService->store($categoryDto);

        return $this->sendResponseForResource(CategoryResource::make($category), __('Category created successfully'));
    }

    public function sort(CategorySortRequest $request): JsonResponse
    {
        $this->categoryService->sort($request->toDto());

        return $this->sendSuccess(__('Categories sorted successfully.'));
    }
}
