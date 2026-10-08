<?php

namespace EscolaLms\Categories\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * App\Models\Category
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $slug
 * @property string|null $icon_class
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $parent_id
 * @property string|null $icon
 * @property int $order
 * @property-read Collection|Category[] $children
 * @property-read int|null $children_count
 * @property-read Collection|Course[] $courses
 * @property-read int|null $courses_count
 * @property-read Category|null $parent
 * @property-read Collection|User[] $users
 * @property-read int|null $users_count
 * @property-read string $name_with_breadcrumbs
 * @method static Builder|Category newModelQuery()
 * @method static Builder|Category newQuery()
 * @method static Builder|Category query()
 * @method static Builder|Category whereCreatedAt($value)
 * @method static Builder|Category whereIcon($value)
 * @method static Builder|Category whereIconClass($value)
 * @method static Builder|Category whereId($value)
 * @method static Builder|Category whereIsActive($value)
 * @method static Builder|Category whereName($value)
 * @method static Builder|Category whereParentId($value)
 * @method static Builder|Category whereSlug($value)
 * @method static Builder|Category whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'description',
        'slug',
        'icon_class',
        'is_active',
        'parent_id',
        'icon',
        'order',
    ];

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->with('children');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(config('auth.providers.users.model'))->withTimestamps();
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(\EscolaLms\Courses\Models\Course::class);
    }

    public function getNameWithBreadcrumbsAttribute(): string
    {
        if ($this->parent) {
            return $this->parent->generateBreadcrumbs([$this->getKey()]) . ucfirst($this->name);
        }
        return $this->name;
    }

    // There is no checking for cycles in parent<->child relation so for safety this method will stop concatenating names when a cycle is found
    protected function generateBreadcrumbs(array $included_ids = []): string
    {
        $result = '';
        if (!in_array($this->getKey(), $included_ids)) {
            $included_ids[] = $this->getKey();
            if ($this->parent) {
                $result .= $this->parent->generateBreadcrumbs($included_ids);
            }
            $result .= ucfirst($this->name) . '. ';
        }
        return $result;
    }


    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (!$category->order) {
                $category->order = 1 + (int) Category::query()
                        ->when($category->parent_id, function (Builder $query, int $parentId) {
                            $query->where('parent_id', $parentId);
                        })
                        ->max('order');
            }
        });
    }
}
