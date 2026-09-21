<?php

namespace App\Models;

use App\Enums\DeliveryMode;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $restaurant_id
 * @property DeliveryMode $delivery_mode
 * @property int|null $delivery_address_id
 * @property OrderStatus $status
 * @property string $subtotal
 * @property string $vat
 * @property string $delivery_fee
 * @property string $total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Restaurant $restaurant
 * @property-read DeliveryAddress|null $deliveryAddress
 * @property-read OrderDish|null $dishesRelation
 * @property-read OrderKitchen|null $kitchensRelation
 */
#[Fillable([
    'user_id', 'restaurant_id', 'delivery_mode', 'delivery_address_id',
    'status', 'subtotal', 'vat', 'delivery_fee', 'total',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return BelongsTo<DeliveryAddress, $this>
     */
    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(DeliveryAddress::class);
    }

    /**
     * @return HasMany<OrderDish, $this>
     */
    public function dishes(): HasMany
    {
        return $this->hasMany(OrderDish::class);
    }

    /**
     * @return HasMany<OrderKitchen, $this>
     */
    public function kitchens(): HasMany
    {
        return $this->hasMany(OrderKitchen::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_mode' => DeliveryMode::class,
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'vat' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
