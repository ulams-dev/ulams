<?php

namespace EscolaLms\Dictionaries\Dtos;

use EscolaLms\Core\Dtos\Contracts\DtoContract;
use EscolaLms\Core\Dtos\Contracts\InstantiateFromRequest;
use Illuminate\Http\Request;

class DictionaryWordDto implements DtoContract, InstantiateFromRequest
{
    private string $word;
    private int $dictionary_id;
    private ?string $description;
    private ?array $data;
    private array $categories;

    public function __construct(string $word, int $dictionary_id, ?string $description, ?array $data, array $categories)
    {
        $this->word = $word;
        $this->dictionary_id = $dictionary_id;
        $this->description = $description;
        $this->data = $data;
        $this->categories = $categories;
    }

    public function toArray(): array
    {
        return [
            'word' => $this->word,
            'dictionary_id' => $this->dictionary_id,
            'description' => $this->description,
            'data' => $this->data,
        ];
    }

    public function getCategories(): array
    {
        return $this->categories;
    }

    public static function instantiateFromRequest(Request $request): self
    {
        return new self(
            $request->input('word'),
            $request->input('dictionary_id'),
            $request->input('description'),
            $request->input('data', []),
            $request->input('categories', []),
        );
    }
}
