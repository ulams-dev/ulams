<?php


namespace EscolaLms\Tags\Dto;


use EscolaLms\Tags\Http\Request\TagInsertRequest;

class TagDto
{
    protected string $modelType;
    protected string $modelName;
    protected int $modelId;
    protected array $tags;

    /**
     * TagDto constructor.
     * @param TagInsertRequest $tagInsertRequest
     */
    public function __construct(TagInsertRequest $tagInsertRequest)
    {
        $this->modelType = $tagInsertRequest->input('model_type');
        $this->modelId = $tagInsertRequest->input('model_id');
        $this->tags = $tagInsertRequest->input('tags');
        $this->setModelName();
    }

    public function setModelName() : void
    {
        $this->modelName = config("escolalms_tags.tag_model_map.{$this->modelType}", null);
    }

    /**
     * @return string|null
     */
    public function getModelName() :? string
    {
        return $this->modelName;
    }

    public function getTags()
    {
        return $this->tags;
    }

    public function getModelId()
    {
        return $this->modelId;
    }
}
