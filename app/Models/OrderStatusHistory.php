<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A recorded transition into a status: the new status, when the order
 * entered it, and how long the order spent in the previous status
 * (ADR-0010). The first row of an order is its entry into PENDING.
 *
 * @property int $id
 * @property int $order_id
 * @property OrderStatus $status
 * @property OrderStatus|null $previous_status
 * @property int|null $duration_in_previous_status seconds spent in the previous status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 */
#[Fillable(['order_id', 'status', 'previous_status', 'duration_in_previous_status'])]
class OrderStatusHistory extends Model
{
    /** @use HasFactory<OrderStatusHistoryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'previous_status' => OrderStatus::class,
            'duration_in_previous_status' => 'integer',
        ];
    }
}
