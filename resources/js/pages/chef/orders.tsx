import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import ChefOrderController from '@/actions/App/Http/Controllers/Chef/ChefOrderController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type DishLine = {
    id: number;
    name_en: string;
    quantity: number;
    status: 'pending' | 'preparing' | 'ready';
};

type KitchenLine = {
    id: number;
    kitchen_id: number;
    name_en: string;
    name_ar: string;
    accepted: boolean;
    dishes: DishLine[];
};

type OrderSummary = {
    id: number;
    status: 'pending' | 'confirmed' | 'preparing';
    delivery_mode: 'delivery' | 'pickup';
    total: string;
    created_at?: string;
    restaurant: { name_en: string };
    kitchens: KitchenLine[];
};

export default function ChefOrders({ orders }: { orders: OrderSummary[] }) {
    const { t } = useTranslation();

    function accept(order: OrderSummary, kitchen: KitchenLine) {
        router.post(
            ChefOrderController.accept({
                order: order.id,
                kitchen: kitchen.kitchen_id,
            }),
            {},
            { preserveScroll: true },
        );
    }

    function markDish(
        dish: DishLine,
        order: number,
        status: 'preparing' | 'ready',
    ) {
        router.patch(
            ChefOrderController.updateDish.patch({ order, orderDish: dish.id }),
            { status },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={t('chef.orders.page_title')} />

            <div className="space-y-6">
                <Heading variant="small" title={t('chef.orders.heading')} />

                {orders.length === 0 ? (
                    <p className="py-8 text-center text-muted-foreground">
                        {t('chef.orders.no_orders')}
                    </p>
                ) : (
                    <div className="space-y-4">
                        {orders.map((order) => (
                            <div
                                key={order.id}
                                className="space-y-3 rounded-lg border p-4"
                            >
                                <div className="flex items-center justify-between gap-4">
                                    <div className="font-medium">
                                        #{order.id} · {order.restaurant.name_en}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant={
                                                order.status === 'pending'
                                                    ? 'secondary'
                                                    : 'default'
                                            }
                                        >
                                            {t(
                                                'chef.orders.status.' +
                                                    order.status,
                                            )}
                                        </Badge>
                                        <span>
                                            {t(
                                                'orders.delivery_mode.' +
                                                    order.delivery_mode,
                                            )}
                                        </span>
                                    </div>
                                </div>

                                {order.kitchens.map((kitchen) => (
                                    <div
                                        key={kitchen.id}
                                        className="space-y-1 rounded border p-3"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <div className="text-sm font-medium">
                                                {kitchen.name_en}
                                            </div>
                                            {kitchen.accepted ? (
                                                <span className="text-sm text-muted-foreground">
                                                    {t('chef.orders.accepted')}
                                                </span>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    disabled={
                                                        order.status !==
                                                        'pending'
                                                    }
                                                    onClick={() =>
                                                        accept(order, kitchen)
                                                    }
                                                >
                                                    {t('chef.orders.accept')}
                                                </Button>
                                            )}
                                        </div>
                                        {kitchen.dishes.length > 0 ? (
                                            <ul className="space-y-1">
                                                {kitchen.dishes.map((dish) => (
                                                    <li
                                                        key={dish.id}
                                                        className="flex items-center justify-between gap-2 text-sm"
                                                    >
                                                        <span>
                                                            {dish.name_en} (
                                                            {dish.quantity})
                                                        </span>
                                                        <span className="flex items-center gap-2">
                                                            <Badge
                                                                variant={
                                                                    dish.status ===
                                                                    'ready'
                                                                        ? 'default'
                                                                        : 'secondary'
                                                                }
                                                            >
                                                                {t(
                                                                    'chef.orders.dish_status.' +
                                                                        dish.status,
                                                                )}
                                                            </Badge>
                                                            {dish.status ===
                                                            'pending' ? (
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    disabled={
                                                                        order.status ===
                                                                        'pending'
                                                                    }
                                                                    onClick={() =>
                                                                        markDish(
                                                                            dish,
                                                                            order.id,
                                                                            'preparing',
                                                                        )
                                                                    }
                                                                >
                                                                    {t(
                                                                        'chef.orders.dish_actions.preparing',
                                                                    )}
                                                                </Button>
                                                            ) : null}
                                                            {dish.status ===
                                                            'preparing' ? (
                                                                <Button
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        markDish(
                                                                            dish,
                                                                            order.id,
                                                                            'ready',
                                                                        )
                                                                    }
                                                                >
                                                                    {t(
                                                                        'chef.orders.dish_actions.ready',
                                                                    )}
                                                                </Button>
                                                            ) : null}
                                                        </span>
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : null}
                                    </div>
                                ))}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
