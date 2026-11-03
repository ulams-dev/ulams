<?php

namespace Ulams\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Ulams\Commerce\Support\Price;
use Ulams\Commerce\Support\SellableRef;

/**
 * What the tenant wants to sell a sellable for. `for_sale` is the tenant's intent; the provider's product is enabled
 * only while it is also published (see `CatalogueSync`).
 *
 * @property int $id
 * @property string $sellable_type
 * @property int $sellable_id
 * @property int $amount_minor
 * @property string $currency
 * @property bool $for_sale
 */
class SellablePrice extends Model
{
    protected $table = 'commerce_sellable_prices';

    protected $guarded = ['id'];

    protected $casts = ['sellable_id' => 'integer', 'amount_minor' => 'integer', 'for_sale' => 'boolean'];

    public function ref(): SellableRef
    {
        return new SellableRef($this->sellable_type, $this->sellable_id);
    }

    public function price(): Price
    {
        return new Price($this->amount_minor, $this->currency);
    }
}
