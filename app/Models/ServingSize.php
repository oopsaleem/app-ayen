<?php

namespace App\Models;

use App\Concerns\HasLocalizedFields;
use Database\Factories\ServingSizeFactory;
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
 * @property bool $is_default
 * @property int $servings_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Dish $dish
 */
#[Fillable(['dish_id', 'name_en', 'name_ar', 'price', 'is_default', 'servings_count'])]
class ServingSize extends Model
{
    /** @use HasFactory<ServingSizeFactory> */
    use HasFactory;

    use HasLocalizedFields;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saved(function (ServingSize $servingSize) {
            if (! $servingSize->is_default) {
                return;
            }

            static::where('dish_id', $servingSize->dish_id)
                ->whereKeyNot($servingSize->id)
                ->update(['is_default' => false]);
        });
    }

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
            'is_default' => 'boolean',
        ];
    }
}
