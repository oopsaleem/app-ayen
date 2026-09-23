import { useState } from 'react';
import {
    addCartItem,
    cartTotal,
    removeCartItem,
    updateCartItemQuantity,
} from '@/lib/order-cart';
import type { CartItem, CartSelection, Dish } from '@/lib/order-cart';

/**
 * Cart state for a restaurant's menu, shared by the public storefront and
 * the authenticated order page: add a dish selection, adjust or remove a
 * line, and read the running total.
 */
export function useOrderCart(initialItems: CartItem[] = []) {
    const [items, setItems] = useState<CartItem[]>(initialItems);

    return {
        items,
        total: cartTotal(items),
        add: (dish: Dish, selection: CartSelection, quantity = 1) =>
            setItems((current) => addCartItem(current, dish, selection, quantity)),
        updateQuantity: (key: string, quantity: number) =>
            setItems((current) => updateCartItemQuantity(current, key, quantity)),
        remove: (key: string) => setItems((current) => removeCartItem(current, key)),
    };
}

export type OrderCart = ReturnType<typeof useOrderCart>;
