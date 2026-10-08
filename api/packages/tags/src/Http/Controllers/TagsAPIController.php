<?php

namespace EscolaLms\Tags\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Tags\Dto\TagDto;
use EscolaLms\Tags\Http\Request\TagInsertRequest;
use EscolaLms\Tags\Http\Request\TagRemoveRequest;
use EscolaLms\Tags\Models\Tag;
use EscolaLms\Tags\Repository\Contracts\TagRepositoryContract;
use EscolaLms\Tags\Services\Contracts\TagServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class TagsAPIController extends EscolaLmsBaseController
{
    private TagServiceContract $tagService;
    private TagRepositoryContract $tagRepository;

    public function __construct(
        TagServiceContract $tagService,
        TagRepositoryContract $tagRepository
    ) {
        $this->tagService = $tagService;
        $this->tagRepository = $tagRepository;
    }

    /**
     * Create multiple Tags.
     * POST|HEAD /tags
     *
     * @param TagInsertRequest $tagInsertRequest
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(TagInsertRequest $tagInsertRequest): JsonResponse
    {
        $tagDto = new TagDto($tagInsertRequest);
        try {
            $tag = $this->tagService->insert($tagDto);
        } catch (Exception $error) {
            return $this->sendError($error->getMessage());
        }
        return $this->sendResponse($tag, 'Tag created successfully');
    }

    /**
     * Display a listing of the Tag.
     * GET|HEAD /tags
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tags = $this->tagRepository->all(
                $request->except(['skip', 'limit']),
                $request->get('skip'),
                $request->get('limit')
            );
        } catch (Exception $error) {
            return $this->sendError($error->getMessage());
        }
        return $this->sendResponse($tags, 'Tags fetched successfully');
    }

    /**
     * Display the specified Tag.
     * GET|HEAD /tags/{tag}
     *
     * @param $id
     * @return JsonResponse
     */
    public function show(Tag $tag, Request $request): JsonResponse
    {
        return empty($tag) ?
            $this->sendError('Tag not found', 404) :
            $this->sendResponse($tag, 'Tag fetched successfully');
    }

    /**
     * Destroy Tags.
     * DELETE|HEAD /tags
     *
     * @param TagRemoveRequest $tagRemoveRequest
     */
    public function destroy(TagRemoveRequest $tagRemoveRequest): JsonResponse
    {
        return $this->tagService->removeTags($tagRemoveRequest->input('tags')) ?
            $this->sendResponse([], 'Tags deleted successfully') : $this->sendError('Tag not found', 404);
    }

    /**
     * Display the unique Tags.
     * GET|HEAD /tags/unique
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function unique(Request $request): JsonResponse
    {
        $tags = $this->tagRepository->unique();
        return $tags ?
            $this->sendResponse($tags, 'Tags unique fetched successfully') :
            $this->sendError('Tags not found', 404) ;
    }

    public function uniqueAdmin(Request $request): JsonResponse
    {
        $tags = $this->tagRepository->unique();
        return $tags ?
            $this->sendResponse($tags, 'Tags unique fetched successfully') :
            $this->sendError('Tags not found', 404) ;
    }
}
