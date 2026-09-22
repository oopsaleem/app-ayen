export type ServingSize = {
    id: number;
    name_en: string;
    name_ar: string;
    price: string;
};

export type DishOption = {
    id: number;
    name_en: string;
    name_ar: string;
    price: string;
};

export type Dish = {
    id: number;
    name_en: string;
    name_ar: string;
    description_en?: string | null;
    price: string;
    serving_sizes: ServingSize[];
    options: DishOption[];
};

export type Line = {
    quantity: number;
    serving_size_id?: number;
    option_ids: number[];
};

export function lineFor(
    lines: Record<number, Line>,
    dishId: number,
): Line {
    return lines[dishId] ?? { quantity: 0, option_ids: [] };
}

export function unitPriceFor(dish: Dish, line: Line): number {
    const selectedSize = dish.serving_sizes.find(
        (size) => size.id === line.serving_size_id,
    );
    const base = Number(dish.price) + Number(selectedSize?.price ?? 0);
    const extras = line.option_ids.reduce(
        (sum, id) =>
            sum +
            Number(
                dish.options.find((option) => option.id === id)?.price ?? 0,
            ),
        0,
    );

    return Number((base + extras).toFixed(2));
}

function cartStorageKey(restaurantId: number): string {
    return `storefront-cart-${restaurantId}`;
}

/**
 * Read the cart persisted for a restaurant (written by the public
 * storefront page before redirecting to login/checkout), clearing it from
 * storage so it is only ever hydrated once. Lines referencing a dish,
 * serving size, or option that no longer exists in `dishes` are dropped;
 * `removedCount` reports how many lines were dropped so the caller can
 * tell the visitor.
 */
export function readStoredCart(
    restaurantId: number,
    dishes: Dish[],
): { lines: Record<number, Line>; removedCount: number } {
    if (typeof window === 'undefined') {
        return { lines: {}, removedCount: 0 };
    }

    const key = cartStorageKey(restaurantId);
    const raw = window.localStorage.getItem(key);

    if (!raw) {
        return { lines: {}, removedCount: 0 };
    }

    window.localStorage.removeItem(key);

    let parsed: Record<string, Line>;

    try {
        parsed = JSON.parse(raw) as Record<string, Line>;
    } catch {
        return { lines: {}, removedCount: 0 };
    }

    const dishById = new Map(dishes.map((dish) => [dish.id, dish]));
    const lines: Record<number, Line> = {};
    let removedCount = 0;

    for (const [dishIdKey, line] of Object.entries(parsed)) {
        const dishId = Number(dishIdKey);
        const dish = dishById.get(dishId);

        if (!dish || line.quantity <= 0) {
            removedCount += 1;
            continue;
        }

        const servingSizeValid =
            line.serving_size_id === undefined ||
            dish.serving_sizes.some(
                (size) => size.id === line.serving_size_id,
            );
        const optionIds = line.option_ids.filter((id) =>
            dish.options.some((option) => option.id === id),
        );

        if (!servingSizeValid || optionIds.length !== line.option_ids.length) {
            removedCount += 1;
        }

        lines[dishId] = {
            quantity: line.quantity,
            serving_size_id: servingSizeValid
                ? line.serving_size_id
                : undefined,
            option_ids: optionIds,
        };
    }

    return { lines, removedCount };
}

/**
 * Persist the current cart for a restaurant so it survives the
 * login/register redirect from the public storefront page to the
 * authenticated checkout page.
 */
export function writeStoredCart(
    restaurantId: number,
    lines: Record<number, Line>,
): void {
    if (typeof window === 'undefined') {
        return;
    }

    const key = cartStorageKey(restaurantId);
    const nonEmpty = Object.fromEntries(
        Object.entries(lines).filter(([, line]) => line.quantity > 0),
    );

    if (Object.keys(nonEmpty).length === 0) {
        window.localStorage.removeItem(key);

        return;
    }

    window.localStorage.setItem(key, JSON.stringify(nonEmpty));
}
