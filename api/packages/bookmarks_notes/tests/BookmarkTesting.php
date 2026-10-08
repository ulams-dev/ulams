<?php

namespace EscolaLms\Bookmarks\Tests;

use EscolaLms\Bookmarks\Models\Bookmark;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;

trait BookmarkTesting
{
    use WithFaker;

    public function bookmarkTable(): string
    {
        return with(new Bookmark())->getTable();
    }

    public function bookmarkPayload(?array $data = []): array
    {
        $type = Str::ucfirst($this->faker->word) . $this->faker->numberBetween();

        $payload = [
            'value' => $this->faker->word,
            'bookmarkable_id' => $this->faker->randomNumber(),
            'bookmarkable_type' => 'EscolaLms\\' . $type . '\\Models\\' . $type,
        ];

        return array_merge($payload, $data);
    }

    public function prepareUri(string $prefix, array $filters): string {
        $uri = $prefix . '?';

        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $uri .= $key . '[]=' . $v . '&';
                }
            }
            else {
                $uri .= $key . '=' . $value . '&';
            }
        }

        return $uri;
    }
}
