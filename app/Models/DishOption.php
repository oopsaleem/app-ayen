<?php

namespace App\Models;

use App\Concerns\HasLocalizedFields;
use Database\Factories\DishOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $dish_id
 * @property string $name_en
 * @property string $name_ar
 * @property string $price
 * @property bool $is_available
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Dish $dish
 */
#[Fillable(['dish_id', 'name_en', 'name_ar', 'price', 'is_available'])]
class DishOption extends Model
{
    /** @use HasFactory<DishOptionFactory> */
    use HasFactory;

    use HasLocalizedFields;

    /**
     * @return BelongsTo<Dish, $this>
     */
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }
}
