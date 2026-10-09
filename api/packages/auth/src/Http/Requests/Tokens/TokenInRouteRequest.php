<?php

namespace Ulams\Auth\Http\Requests\Tokens;

use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\Auth\Models\ApiTokenMeta;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;

/** Base for endpoints addressing one scoped token as `{id}`; only scoped tokens are addressable. */
abstract class TokenInRouteRequest extends FormRequest
{
    /** Policy ability checked on the token. */
    abstract protected function ability(): string;

    public function tokenMeta(): ApiTokenMeta
    {
        $id = (string) $this->route('id');

        return (preg_match('/^[0-9a-zA-Z]{1,100}$/', $id) ? app(PersonalAccessTokenServiceContract::class)->find($id) : null)
            ?? throw new NotFoundHttpException('Token not found.');
    }

    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can($this->ability(), $this->tokenMeta());
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [];
    }
}
