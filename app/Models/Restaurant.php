<?php

namespace App\Models;

use App\Concerns\HasLocalizedFields;
use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $company_id
 * @property string $slug
 * @property string $name_en
 * @property string $name_ar
 * @property string|null $description_en
 * @property string|null $description_ar
 * @property array<int, string>|null $images
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 * @property-read RestaurantAddress|null $address
 * @property-read RestaurantVerification|null $verification
 * @property-read Collection<int, Kitchen> $kitchens
 * @property-read Collection<int, Category> $categories
 */
#[Fillable(['company_id', 'slug', 'name_en', 'name_ar', 'description_en', 'description_ar', 'images'])]
class Restaurant extends Model
{
    /** @use HasFactory<RestaurantFactory> */
    use HasFactory;

    use HasLocalizedFields;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Restaurant $restaurant): void {
            if (empty($restaurant->slug)) {
                $restaurant->slug = static::generateUniqueSlug($restaurant->name_en);
            }
        });

        static::updating(function (Restaurant $restaurant): void {
            if ($restaurant->isDirty('name_en')) {
                $restaurant->slug = static::generateUniqueSlug($restaurant->name_en, $restaurant->id);
            }
        });
    }

    /**
     * Generate a unique slug for the restaurant, matching the suffixing
     * scheme Team uses for its own slugs.
     */
    protected static function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $defaultSlug = Str::slug($name);

        $query = static::query()
            ->where(function ($query) use ($defaultSlug) {
                $query->where('slug', $defaultSlug)
                    ->orWhere('slug', 'like', $defaultSlug.'-%');
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $existingSlugs = $query->pluck('slug');

        $maxSuffix = $existingSlugs
            ->map(function (string $slug) use ($defaultSlug): ?int {
                if ($slug === $defaultSlug) {
                    return 0;
                } elseif (preg_match('/^'.preg_quote($defaultSlug, '/').'-(\d+)$/', $slug, $matches)) {
                    return (int) $matches[1];
                }

                return null;
            })
            ->filter(fn (?int $suffix) => $suffix !== null)
            ->max() ?? 0;

        return $existingSlugs->isEmpty()
            ? $defaultSlug
            : $defaultSlug.'-'.($maxSuffix + 1);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasOne<RestaurantAddress, $this>
     */
    public function address(): HasOne
    {
        return $this->hasOne(RestaurantAddress::class);
    }

    /**
     * @return HasOne<RestaurantVerification, $this>
     */
    public function verification(): HasOne
    {
        return $this->hasOne(RestaurantVerification::class);
    }

    /**
     * @return HasMany<Kitchen, $this>
     */
    public function kitchens(): HasMany
    {
        return $this->hasMany(Kitchen::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'images' => 'array',
        ];
    }
}
