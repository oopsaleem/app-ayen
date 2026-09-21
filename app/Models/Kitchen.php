<?php

namespace App\Models;

use App\Concerns\HasLocalizedFields;
use Database\Factories\KitchenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $restaurant_id
 * @property string $name_en
 * @property string $name_ar
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Restaurant $restaurant
 * @property-read Collection<int, Dish> $dishes
 * @property-read Collection<int, Chef> $chefs
 */
#[Fillable(['restaurant_id', 'name_en', 'name_ar'])]
class Kitchen extends Model
{
    /** @use HasFactory<KitchenFactory> */
    use HasFactory;

    use HasLocalizedFields;

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return HasMany<Dish, $this>
     */
    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }

    /**
     * @return BelongsToMany<Chef, $this, ChefKitchen, 'pivot'>
     */
    public function chefs(): BelongsToMany
    {
        return $this->belongsToMany(Chef::class, 'chef_kitchen')
            ->using(ChefKitchen::class)
            ->withPivot(['assigned_at', 'assigned_by'])
            ->withTimestamps(false, false);
    }
}
