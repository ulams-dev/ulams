<?php

namespace Ulams\Auth\Http\Requests\Device;

use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ulams\Auth\Models\DeviceAuthorization;
use Ulams\Auth\Services\Contracts\DeviceAuthorizationServiceContract;

/** Base for the approval endpoints addressing a pending request by the `{user_code}` a person typed. */
abstract class DeviceRequestInRouteRequest extends FormRequest
{
    abstract protected function ability(): string;

    public function authorization(): DeviceAuthorization
    {
        return app(DeviceAuthorizationServiceContract::class)->findPendingByUserCode((string) $this->route('user_code'))
            ?? throw new NotFoundHttpException('This code is unknown or has expired.');
    }

    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can($this->ability(), $this->authorization());
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [];
    }
}
