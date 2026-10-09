<?php

namespace Ulams\Lrs\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Ulams\Lrs\Models\Access;
use Ulams\Lrs\Models\XapiDocument;
use Ulams\Lrs\Services\Contracts\XapiDocumentServiceContract;
use Ulams\Lrs\Xapi\DocumentScope;
use Ulams\Lrs\Xapi\StatementValidator;
use Ulams\Lrs\Xapi\XapiException;

class XapiDocumentService implements XapiDocumentServiceContract
{
    public function find(DocumentScope $scope, Access $access): ?XapiDocument
    {
        return $this->query($scope, $access)->first();
    }

    public function ids(DocumentScope $scope, Access $access, ?string $since = null): array
    {
        $query = $this->query($scope->withoutDocumentId(), $access);

        if ($since !== null) {
            if (!StatementValidator::isTimestamp($since)) {
                throw XapiException::badRequest('The [since] parameter must be an ISO 8601 timestamp.');
            }
            $query->where('updated_at', '>', Carbon::parse($since)->utc());
        }

        $column = $scope->model::documentIdColumn();

        return $query->orderBy($column)->pluck($column)->all();
    }

    public function save(
        DocumentScope $scope,
        Access $access,
        string $content,
        string $contentType,
        bool $merge,
        ?string $ifMatch = null,
        ?string $ifNoneMatch = null,
    ): XapiDocument {
        $isJson = $this->isJson($contentType);
        $value = $this->decode($content, $isJson);

        return DB::transaction(function () use ($scope, $access, $value, $contentType, $isJson, $merge, $ifMatch, $ifNoneMatch) {
            $document = $this->query($scope, $access)->lockForUpdate()->first();
            $this->checkPreconditions($document, $ifMatch, $ifNoneMatch);

            if ($document && $merge) {
                $current = $document->data;
                if (!$isJson || !$this->isJson((string) ($current->type ?? ''))) {
                    throw XapiException::badRequest('Only JSON documents can be merged (POST); use PUT to replace.');
                }
                if (!StatementValidator::isObject($value)) {
                    throw XapiException::badRequest('A merged document must be a JSON object.');
                }
                $existing = json_decode(json_encode($current->content ?? new \stdClass()), true);
                if (!is_array($existing) || !StatementValidator::isObject($existing)) {
                    throw XapiException::badRequest('The stored document is not a JSON object and can not be merged.');
                }
                $value = array_merge($existing, $value);
            } elseif ($merge && $isJson && !StatementValidator::isObject($value)) {
                throw XapiException::badRequest('A merged document must be a JSON object.');
            }

            $document ??= (new $scope->model())->forceFill($scope->attributes() + ['owner_id' => $access->ownerId()]);
            $document->data = ['content' => $value === [] && $isJson ? new \stdClass() : $value, 'type' => $contentType];
            $document->timestamp = Carbon::now()->utc()->format('Y-m-d\TH:i:s.v\Z');
            $document->save();

            return $document;
        });
    }

    public function delete(DocumentScope $scope, Access $access, ?string $ifMatch = null): void
    {
        if ($scope->documentId === null) {
            $this->query($scope, $access)->delete();

            return;
        }

        $document = $this->find($scope, $access);
        $this->checkPreconditions($document, $ifMatch, null);
        $document?->delete();
    }

    public function etag(XapiDocument $document): string
    {
        return '"' . sha1($this->body($document)) . '"';
    }

    public function body(XapiDocument $document): string
    {
        $content = $document->data->content ?? '';

        return is_string($content) && !$this->isJson((string) ($document->data->type ?? ''))
            ? $content
            : (string) json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function query(DocumentScope $scope, Access $access): Builder
    {
        $query = $scope->model::query()->where('owner_id', $access->ownerId());

        foreach ($scope->attributes() as $column => $value) {
            $value === null ? $query->whereNull($column) : $query->where($column, $value);
        }

        return $query;
    }

    private function checkPreconditions(?XapiDocument $document, ?string $ifMatch, ?string $ifNoneMatch): void
    {
        if ($ifMatch !== null && $ifMatch !== '') {
            $tags = array_map('trim', explode(',', $ifMatch));
            if (!$document || (!in_array('*', $tags, true) && !in_array($this->etag($document), $tags, true))) {
                throw XapiException::preconditionFailed('The document does not match If-Match.');
            }
        }

        if ($ifNoneMatch !== null && trim($ifNoneMatch) === '*' && $document) {
            throw XapiException::preconditionFailed('The document already exists (If-None-Match: *).');
        }
    }

    private function isJson(string $contentType): bool
    {
        return str_starts_with(strtolower(trim($contentType)), 'application/json');
    }

    private function decode(string $content, bool $isJson): mixed
    {
        if (!$isJson) {
            if (!mb_check_encoding($content, 'UTF-8')) {
                throw XapiException::badRequest('Binary documents are not supported; send text or JSON.');
            }

            return $content;
        }

        $value = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw XapiException::badRequest('The document is not valid JSON.');
        }

        return $value;
    }
}
