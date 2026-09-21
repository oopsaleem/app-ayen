<?php

namespace App\Models;

use App\Enums\DeliveryMode;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

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
 * @property-read Collection<int, OrderDish> $dishes
 * @property-read Collection<int, OrderKitchen> $kitchens
 * @property-read Collection<int, OrderStatusHistory> $statusHistory
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
     * Record the order's entry into its initial status so the status history
     * spans every status from day one (ADR-0010).
     */
    protected static function booted(): void
    {
        static::created(function (Order $order): void {
            $order->statusHistory()->create(['status' => $order->status]);
        });
    }

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
     * @return HasMany<OrderStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * Move the order to a new status and record the transition in its
     * status history, with the time spent in the previous status
     * (ADR-0010). Illegal transitions raise an exception.
     */
    public function transitionTo(OrderStatus $next): OrderStatusHistory
    {
        if (! $this->status->canTransitionTo($next)) {
            throw new LogicException(
                'Orders cannot transition from '.$this->status->value.' to '.$next->value.'.',
            );
        }

        $previous = $this->status;
        $enteredPreviousAt = $this->statusHistory()->latest('id')->first()->created_at;

        $duration = (int) abs(round(now()->diffInSeconds($enteredPreviousAt)));

        $this->forceFill(['status' => $next])->save();

        return $this->statusHistory()->create([
            'status' => $next,
            'previous_status' => $previous,
            'duration_in_previous_status' => $duration,
        ]);
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
