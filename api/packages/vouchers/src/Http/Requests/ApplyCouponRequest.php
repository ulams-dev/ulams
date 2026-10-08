<?php

namespace EscolaLms\Vouchers\Http\Requests;

use EscolaLms\Vouchers\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ApplyCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('apply', $this->getCoupon());
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'code' => 'string'
        ];
    }

    public function getCoupon(): Coupon
    {
        return Coupon::where('code', $this->input('code'))->firstOrFail();
    }
}
