<?php

namespace App\Models;

use Database\Factories\ChefFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $display_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Kitchen> $kitchens
 */
#[Fillable(['user_id', 'display_name'])]
class Chef extends Model
{
    /** @use HasFactory<ChefFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Kitchen, $this, ChefKitchen, 'pivot'>
     */
    public function kitchens(): BelongsToMany
    {
        return $this->belongsToMany(Kitchen::class, 'chef_kitchen')
            ->using(ChefKitchen::class)
            ->withPivot(['assigned_at', 'assigned_by'])
            ->withTimestamps(false, false);
    }
}
