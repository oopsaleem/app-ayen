<?php

namespace App\Models;

use App\Concerns\HasLocalizedFields;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $restaurant_id
 * @property string $name_en
 * @property string $name_ar
 * @property string|null $description_en
 * @property string|null $description_ar
 * @property string|null $image
 * @property bool $is_active
 * @property int $level
 * @property int|null $parent_id
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Restaurant $restaurant
 * @property-read Category|null $parent
 * @property-read Collection<int, Category> $children
 * @property-read Collection<int, Dish> $dishes
 */
#[Fillable([
    'restaurant_id', 'name_en', 'name_ar', 'description_en', 'description_ar',
    'image', 'is_active', 'parent_id', 'order',
])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasLocalizedFields;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Category $category) {
            $category->level = $category->parent_id
                ? static::findOrFail($category->parent_id)->level + 1
                : 1;

            if ($category->parent_id) {
                $category->guardAgainstCycle();
            }
        });
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Dish, $this>
     */
    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }

    /**
     * Reject a parent_id change that would make this category its own
     * ancestor (directly or transitively).
     */
    protected function guardAgainstCycle(): void
    {
        $ancestorId = $this->parent_id;
        $visited = [];

        while ($ancestorId !== null) {
            if ($ancestorId === $this->id) {
                throw new InvalidArgumentException('A category cannot be its own ancestor.');
            }

            if (in_array($ancestorId, $visited, true)) {
                break;
            }

            $visited[] = $ancestorId;
            $ancestorId = static::whereKey($ancestorId)->value('parent_id');
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
