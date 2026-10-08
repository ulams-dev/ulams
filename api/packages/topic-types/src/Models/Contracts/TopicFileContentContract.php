<?php

namespace EscolaLms\TopicTypes\Models\Contracts;

use Illuminate\Foundation\Http\FormRequest;

interface TopicFileContentContract
{
    public function getFileKeyNames(): array;

    public function getUrlAttribute(): string;

    public function generateStoragePath(?string $basePath = null): string;

    public function getStoragePathFinalSegment(): string;

    public function storeUploadsFromRequest(FormRequest $request, ?string $path = null): self;
}
