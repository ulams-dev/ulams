<?php

namespace Ulams\Cart\Models;

use Ulams\Cart\Contracts\Productable;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Throwable;

/**
 * Ulams\Cart\Models\ProductProductable
 *
 * @property int $id
 * @property int $product_id
 * @property string $productable_type
 * @property int $productable_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $quantity
 * @property ?int $position
 * @property-read Productable|null $canonical_productable
 * @property-read \Ulams\Cart\Models\Product|null $product
 * @property-read Model|\Eloquent $productable
 * @method static \Illuminate\Database\Eloquent\Builder|ProductProductable newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ProductProductable newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ProductProductable query()
 * @method static \Illuminate\Database\Eloquent\Builder|ProductProductable whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductProductable whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductProductable whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductProductable whereProductableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductProductable whereProductableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductProductable whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ProductProductable whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ProductProductable extends Model
{
    protected $table = 'products_productables';

    protected $guarded = ['id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Memoised: list resources read the canonical productable several times per row. */
    private ?Productable $canonicalProductable = null;

    public function getCanonicalProductableAttribute(): ?Productable
    {
        if ($this->canonicalProductable !== null) {
            return $this->canonicalProductable;
        }
        $productable = $this->productable;
        if ($productable instanceof Productable) {
            return $this->canonicalProductable = $productable;
        }
        if (!$productable instanceof Model) {
            return null;
        }
        try {
            $service = app(ProductServiceContract::class);
            $class = $service->canonicalProductableClass(get_class($productable));
            $canonical = new $class();
            // The canonical productable class extends the loaded model and shares its table:
            // build it from the row already loaded instead of fetching it again.
            if ($canonical instanceof Model && $canonical->getTable() === $productable->getTable()) {
                $model = $canonical->newFromBuilder($productable->getAttributes(), $productable->getConnectionName());
                $model->setRelations($productable->getRelations());
                if ($model instanceof Productable) {
                    return $this->canonicalProductable = $model;
                }
            }

            return $this->canonicalProductable = $service->findProductable(get_class($productable), $productable->getKey());
        } catch (Throwable $ex) {
            // do nothing
        }
        return null;
    }
}
