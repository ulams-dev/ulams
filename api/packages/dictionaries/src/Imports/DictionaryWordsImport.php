<?php

namespace EscolaLms\Dictionaries\Imports;

use EscolaLms\Dictionaries\Models\Category;
use EscolaLms\Dictionaries\Models\DictionaryWord;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DictionaryWordsImport implements ToModel, WithValidation, SkipsEmptyRows, WithStartRow
{
    public function __construct(private readonly int $dictionaryId)
    {
    }

    public function model(array $row): DictionaryWord
    {
        /** @var DictionaryWord $word */
        $word = DictionaryWord::query()->updateOrCreate([
            'dictionary_id' => $this->dictionaryId,
            'word' => $row[0],
        ], [
            'description' => $row[1],
            'data' => ['descriptions' => $row[3]],
        ]);

        $categories = Category::query()->whereIn('name', $row[2])->get();
        $word->categories()->sync($categories);

        return $word;
    }

    public function rules(): array
    {
        return [
            '*.0' => ['required', 'max:255'],
            '*.1' => ['nullable', 'string'],
            '*.2' => ['nullable', 'array'],
            '*.2.*' => ['string', 'exists:categories,name'],
            '*.3' => ['nullable', 'array'],
        ];
    }

    public function prepareForValidation($data, $index): array
    {
        $data[2] = collect(explode(',', $data[2]))
            ->map(fn(string $categoryName) => trim($categoryName))
            ->filter()
            ->toArray();

        $data[3] = collect($data)
            ->slice(3)
            ->chunk(3)
            ->map(fn(Collection $items) => $items->values())
            ->map(fn(Collection $items) => [
                'title' => $items->get(0),
                'description' => $items->get(1),
                'video_url' => $items->get(2),
            ])
            ->values()
            ->toArray();

        return $data;
    }

    public function startRow(): int
    {
        return 2;
    }
}
