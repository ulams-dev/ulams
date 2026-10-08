<?php

namespace EscolaLms\StationaryEvents\Http\Requests;

use EscolaLms\Files\Rules\FileOrStringRule;
use EscolaLms\StationaryEvents\Enum\ConstantEnum;
use EscolaLms\StationaryEvents\Enum\StationaryEventStatusEnum;
use EscolaLms\StationaryEvents\Models\StationaryEvent;
use EscolaLms\StationaryEvents\Rules\ValidAuthor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *      schema="stationary-event-update-request",
 *      @OA\Property(
 *          property="name",
 *          description="name",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="status",
 *          description="status",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="description",
 *          description="description",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="short_desc",
 *          description="short description",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="started_at",
 *          description="started_at",
 *          type="datetime"
 *      ),
 *      @OA\Property(
 *          property="finished_at",
 *          description="finished_at",
 *          type="datetime",
 *      ),
 *      @OA\Property(
 *          property="max_participants",
 *          description="max_participants",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="place",
 *          description="place",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="program",
 *          description="program",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="authors",
 *          description="authors",
 *          type="array",
 *          @OA\Items(
 *              type="integer",
 *          )
 *      ),
 *      @OA\Property(
 *          property="categories",
 *          description="categories",
 *          type="array",
 *          @OA\Items(
 *              type="integer",
 *          )
 *      ),
 *      @OA\Property(
 *          property="image",
 *          description="image",
 *          type="file",
 *      ),
 *      @OA\Property(
 *          property="image_path",
 *          description="image_path",
 *          type="string",
 *      ),
 *      @OA\Property(
 *          property="agenda",
 *          description="agenda",
 *          type="object",
 *      ),
 * )
 *
 */
class UpdateStationaryEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->getStationaryEvent());
    }

    public function rules(): array
    {
        $prefixPath = ConstantEnum::DIRECTORY . '/' . $this->route('id');

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'status' => ['string', Rule::in(StationaryEventStatusEnum::getValues())],
            'description' => ['nullable', 'string'],
            'short_desc' => ['nullable', 'string', 'min:3'],
            'started_at' => ['nullable', 'date', 'after_or_equal:' . date('Y-m-d')],
            'finished_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'base_price' => ['nullable', 'integer', 'min:0'],
            'max_participants' => ['nullable', 'integer', 'min:0'],
            'place' => ['nullable', 'string', 'max:255'],
            'program' => ['nullable', 'string', 'max:255'],
            'authors' => ['nullable', 'array'],
            'authors.*' => ['integer', new ValidAuthor()],
            'categories' => ['array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'image' => [new FileOrStringRule(['image'], $prefixPath)],
            'image_path' => ['nullable', 'string', 'max:255'],
            'agenda' => ['nullable', 'json'],
        ];
    }

    public function getStationaryEvent(): StationaryEvent
    {
        return StationaryEvent::findOrFail($this->route('id'));
    }
}
