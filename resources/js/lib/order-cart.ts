export type ServingSize = {
    id: number;
    name_en: string;
    name_ar: string;
    price: string;
    is_default: boolean;
    servings_count: number;
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
    description_ar?: string | null;
    price: string;
    image_urls: string[];
    serving_sizes: ServingSize[];
    options: DishOption[];
};

export type Category = {
    id: number;
    name_en: string;
    name_ar: string;
    dishes: Dish[];
    children: Category[];
};

/** A dish's chosen serving size and add-ons, before it becomes a cart line. */
export type CartSelection = {
    serving_size_id?: number;
    option_ids: number[];
};

/** One cart line: a dish with a specific selection and a quantity. */
export type CartItem = CartSelection & {
    key: string;
    dish: Dish;
    quantity: number;
};

/**
 * Identifies a dish + selection combination, so the same dish can sit in
 * the cart multiple times with different serving sizes or options while
 * an identical selection merges into one line.
 */
export function cartItemKey(dishId: number, selection: CartSelection): string {
    const sortedOptionIds = [...selection.option_ids].sort((a, b) => a - b);

    return [dishId, selection.serving_size_id ?? 'none', sortedOptionIds.join(',')].join('|');
}

export function unitPriceFor(dish: Dish, selection: CartSelection): number {
    const selectedSize = dish.serving_sizes.find(
        (size) => size.id === selection.serving_size_id,
    );
    const base = Number(dish.price) + Number(selectedSize?.price ?? 0);
    const extras = selection.option_ids.reduce(
        (sum, id) =>
            sum +
            Number(dish.options.find((option) => option.id === id)?.price ?? 0),
        0,
    );

    return Number((base + extras).toFixed(2));
}

export function lineTotal(item: CartItem): number {
    return Number((unitPriceFor(item.dish, item) * item.quantity).toFixed(2));
}

export function cartTotal(items: CartItem[]): number {
    return Number(
        items.reduce((sum, item) => sum + lineTotal(item), 0).toFixed(2),
    );
}

/**
 * Add a dish selection to the cart: an existing line with the same dish
 * and selection gets its quantity bumped, otherwise a new line is added.
 */
export function addCartItem(
    items: CartItem[],
    dish: Dish,
    selection: CartSelection,
    quantity = 1,
): CartItem[] {
    const key = cartItemKey(dish.id, selection);
    const existing = items.find((item) => item.key === key);

    if (existing) {
        return updateCartItemQuantity(items, key, existing.quantity + quantity);
    }

    return [...items, { key, dish, ...selection, quantity }];
}

/** Set a line's quantity; a quantity of zero or less removes the line. */
export function updateCartItemQuantity(
    items: CartItem[],
    key: string,
    quantity: number,
): CartItem[] {
    if (quantity <= 0) {
        return removeCartItem(items, key);
    }

    return items.map((item) => (item.key === key ? { ...item, quantity } : item));
}

export function removeCartItem(items: CartItem[], key: string): CartItem[] {
    return items.filter((item) => item.key !== key);
}

/**
 * Every dish reachable in the category tree, keyed by id, so a stored
 * cart line can be revalidated against the menu currently on offer.
 */
function flattenDishes(categories: Category[]): Map<number, Dish> {
    const dishes = new Map<number, Dish>();

    const visit = (category: Category) => {
        category.dishes.forEach((dish) => dishes.set(dish.id, dish));
        category.children.forEach(visit);
    };

    categories.forEach(visit);

    return dishes;
}

function cartStorageKey(restaurantId: number): string {
    return `storefront-cart-${restaurantId}`;
}

type StoredCartLine = {
    dish_id: number;
    serving_size_id?: number;
    option_ids: number[];
    quantity: number;
};

/**
 * Read the cart persisted for a restaurant (written by the public
 * storefront page before redirecting to login/checkout), clearing it from
 * storage so it is only ever hydrated once. Lines referencing a dish,
 * serving size, or option that no longer exists in `categories` are
 * dropped; `removedCount` reports how many lines were dropped so the
 * caller can tell the visitor.
 */
export function readStoredCart(
    restaurantId: number,
    categories: Category[],
): { items: CartItem[]; removedCount: number } {
    if (typeof window === 'undefined') {
        return { items: [], removedCount: 0 };
    }

    const key = cartStorageKey(restaurantId);
    const raw = window.localStorage.getItem(key);

    if (!raw) {
        return { items: [], removedCount: 0 };
    }

    window.localStorage.removeItem(key);

    let parsed: StoredCartLine[];

    try {
        parsed = JSON.parse(raw) as StoredCartLine[];
    } catch {
        return { items: [], removedCount: 0 };
    }

    const dishById = flattenDishes(categories);
    const items: CartItem[] = [];
    let removedCount = 0;

    for (const line of parsed) {
        const dish = dishById.get(line.dish_id);

        if (!dish || line.quantity <= 0) {
            removedCount += 1;
            continue;
        }

        const servingSizeValid =
            line.serving_size_id === undefined ||
            dish.serving_sizes.some((size) => size.id === line.serving_size_id);
        const optionIds = line.option_ids.filter((id) =>
            dish.options.some((option) => option.id === id),
        );

        if (!servingSizeValid || optionIds.length !== line.option_ids.length) {
            removedCount += 1;
        }

        const selection: CartSelection = {
            serving_size_id: servingSizeValid ? line.serving_size_id : undefined,
            option_ids: optionIds,
        };

        items.push({
            key: cartItemKey(dish.id, selection),
            dish,
            ...selection,
            quantity: line.quantity,
        });
    }

    return { items, removedCount };
}

/**
 * Persist the current cart for a restaurant so it survives the
 * login/register redirect from the public storefront page to the
 * authenticated checkout page.
 */
export function writeStoredCart(restaurantId: number, items: CartItem[]): void {
    if (typeof window === 'undefined') {
        return;
    }

    const key = cartStorageKey(restaurantId);
    const lines: StoredCartLine[] = items
        .filter((item) => item.quantity > 0)
        .map((item) => ({
            dish_id: item.dish.id,
            serving_size_id: item.serving_size_id,
            option_ids: item.option_ids,
            quantity: item.quantity,
        }));

    if (lines.length === 0) {
        window.localStorage.removeItem(key);

        return;
    }

    window.localStorage.setItem(key, JSON.stringify(lines));
}
