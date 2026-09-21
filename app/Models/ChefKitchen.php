<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $chef_id
 * @property int $kitchen_id
 * @property Carbon|null $assigned_at
 * @property int|null $assigned_by
 * @property-read Chef $chef
 * @property-read Kitchen $kitchen
 * @property-read User|null $assignedBy
 */
#[Fillable(['chef_id', 'kitchen_id', 'assigned_at', 'assigned_by'])]
class ChefKitchen extends Pivot
{
    /**
     * @var string
     */
    protected $table = 'chef_kitchen';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return BelongsTo<Chef, $this>
     */
    public function chef(): BelongsTo
    {
        return $this->belongsTo(Chef::class);
    }

    /**
     * @return BelongsTo<Kitchen, $this>
     */
    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }
}
