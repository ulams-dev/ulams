<?php

namespace EscolaLms\Bookmarks\Dtos;

use Illuminate\Http\Request;

class UpdateBookmarkDto extends CreateBookmarkDto
{
    private int $id;

    public function __construct(int $id, ?string $value, string $bookmarkableType, int $bookmarkableId, ?int $userId = null)
    {
        parent::__construct($value, $bookmarkableType, $bookmarkableId, $userId);

        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function toArray(): array
    {
        return parent::toArray() + [
            'id' => $this->getId(),
        ];
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new self(
            $request->route('id'),
            $request->input('value'),
            $request->input('bookmarkable_type'),
            $request->input('bookmarkable_id'),
            $request->input('user_id'),
        );
    }
}
