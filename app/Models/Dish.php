<?php

namespace App\Models;

use App\Concerns\HasLocalizedFields;
use Database\Factories\DishFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $category_id
 * @property int $kitchen_id
 * @property string $name_en
 * @property string $name_ar
 * @property string|null $description_en
 * @property string|null $description_ar
 * @property string $price
 * @property bool $is_available
 * @property array<int, string>|null $images
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category $category
 * @property-read Kitchen $kitchen
 * @property-read Collection<int, DishOption> $options
 * @property-read Collection<int, ServingSize> $servingSizes
 */
#[Fillable([
    'category_id', 'kitchen_id', 'name_en', 'name_ar', 'description_en',
    'description_ar', 'price', 'is_available', 'images',
])]
class Dish extends Model
{
    /** @use HasFactory<DishFactory> */
    use HasFactory;

    use HasLocalizedFields;

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Kitchen, $this>
     */
    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    /**
     * @return HasMany<DishOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(DishOption::class);
    }

    /**
     * @return HasMany<ServingSize, $this>
     */
    public function servingSizes(): HasMany
    {
        return $this->hasMany(ServingSize::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'images' => 'array',
        ];
    }
}
