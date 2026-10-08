<?php

namespace EscolaLms\Vouchers\Http\Requests;

use EscolaLms\Vouchers\Dtos\CouponSearchDto;
use EscolaLms\Vouchers\Enums\CouponTypeEnum;
use EscolaLms\Vouchers\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListCouponsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Coupon::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string'],
            'code' => ['sometimes', 'string'],
            'type' => ['sometimes', 'string', Rule::in(CouponTypeEnum::getValues())],
            'active_from' => ['sometimes', 'date'],
            'active_to' => ['sometimes', 'date'],
            'page' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer'],
            'order_by' => ['sometimes', Rule::in(['id', 'created_at', 'updated_at', 'active_from', 'active_to', 'name', 'code', 'amount', 'limit_usage', 'limit_per_user', 'min_cart_price', 'max_cart_price'])],
            'order' => ['sometimes', Rule::in(['ASC', 'DESC'])],
        ];
    }

    public function toDto(): CouponSearchDto
    {
        return new CouponSearchDto(
            $this->input('name'),
            $this->input('code'),
            $this->input('type'),
            $this->has('active_from') ? Carbon::parse($this->input('active_from')) : null,
            $this->has('active_to') ? Carbon::parse($this->input('active_to')) : null,
            $this->input('per_page')
        );
    }
}
