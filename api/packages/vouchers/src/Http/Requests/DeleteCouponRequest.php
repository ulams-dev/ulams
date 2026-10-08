<?php

namespace EscolaLms\Vouchers\Http\Requests;

use EscolaLms\Vouchers\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DeleteCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('delete', $this->getCoupon());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    public function getCoupon(): Coupon
    {
        return Coupon::findOrFail($this->route('id'));
    }
}
