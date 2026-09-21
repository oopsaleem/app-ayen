<?php

namespace App\Models;

use App\Enums\OrderDishStatus;
use Database\Factories\OrderDishFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int $dish_id
 * @property int|null $serving_size_id
 * @property int $quantity
 * @property string $unit_price
 * @property string $total_price
 * @property OrderDishStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 * @property-read Dish $dish
 * @property-read ServingSize|null $servingSize
 */
#[Fillable([
    'order_id', 'dish_id', 'serving_size_id', 'quantity',
    'unit_price', 'total_price', 'status',
])]
class OrderDish extends Model
{
    /** @use HasFactory<OrderDishFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Dish, $this>
     */
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    /**
     * @return BelongsTo<ServingSize, $this>
     */
    public function servingSize(): BelongsTo
    {
        return $this->belongsTo(ServingSize::class);
    }

    /**
     * The options selected for this order line, with the unit price
     * snapshotted at the time the order was placed.
     *
     * @return BelongsToMany<DishOption, $this>
     */
    public function options(): BelongsToMany
    {
        return $this->belongsToMany(DishOption::class, 'order_dish_option')
            ->withPivot('unit_price');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderDishStatus::class,
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }
}
