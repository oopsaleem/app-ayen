<?php

namespace App\Models;

use Database\Factories\RestaurantVerificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $restaurant_id
 * @property int|null $verified_by_admin_id
 * @property bool $verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Restaurant $restaurant
 * @property-read Admin|null $verifiedByAdmin
 */
#[Fillable(['restaurant_id', 'verified_by_admin_id', 'verified'])]
class RestaurantVerification extends Model
{
    /** @use HasFactory<RestaurantVerificationFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $primaryKey = 'restaurant_id';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'verified_by_admin_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
        ];
    }
}
