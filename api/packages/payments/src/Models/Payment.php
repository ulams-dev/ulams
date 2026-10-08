<?php

namespace EscolaLms\Payments\Models;

use EscolaLms\Payments\Enums\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Money\Money;
use EscolaLms\Payments\Enums\PaymentStatus;
use EscolaLms\Payments\Models\Schemas\PaymentSchema;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EscolaLms\Payments\Models\Payment
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $amount
 * @property mixed $currency
 * @property string|null $description
 * @property string|null $order_id
 * @property mixed $status
 * @property string|null $payable_type
 * @property int|null $payable_id
 * @property int|null $user_id
 * @property string|null $driver
 * @property string|null $gateway_order_id
 * @property string|null $redirect_url
 * @property boolean|null $recursive
 * @property boolean|null $refund
 * @property string|null $gateway_request_id
 * @property string|null $gateway_refunds_uuid
 * @property-read Model|\Eloquent $payable
 * @property-read \EscolaLms\Payments\Models\User|null $user
 * @method static \Database\Factories\EscolaLms\Payments\Models\PaymentFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereBillableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereDriver($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereGatewayOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment wherePayableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment wherePayableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereRedirectUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereUserId($value)
 * @mixin \Eloquent
 */
class Payment extends Model implements PaymentSchema
{
    use HasFactory;

    protected $guarded = [
        'id'
    ];

    protected $casts = [
        'currency' => Currency::class,
        'status' => PaymentStatus::class,
        'refund' => 'boolean',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPaymentMoney(): Money
    {
        return new Money($this->amount, $this->currency->toMoneyCurrency());
    }
}
