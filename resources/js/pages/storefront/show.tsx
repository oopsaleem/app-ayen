import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AppDirectionProvider from '@/components/app-direction-provider';
import { DishSelectorCard } from '@/components/dish-selector-card';
import LanguageSwitcher from '@/components/language-switcher';
import { Button } from '@/components/ui/button';
import { lineFor, writeStoredCart } from '@/lib/order-cart';
import type { Dish, Line } from '@/lib/order-cart';
import { order } from '@/routes/restaurants';

type Restaurant = {
    id: number;
    name_en: string;
    description_en?: string | null;
};

export default function StorefrontShow({
    restaurant,
    dishes,
}: {
    restaurant: Restaurant;
    dishes: Dish[];
}) {
    const { t } = useTranslation();
    const [lines, setLines] = useState<Record<number, Line>>({});

    function setLine(dishId: number, line: Line) {
        setLines((current) => ({ ...current, [dishId]: line }));
    }

    const hasItems = Object.values(lines).some((line) => line.quantity > 0);

    function handleCheckout() {
        writeStoredCart(restaurant.id, lines);
        router.visit(order.url(restaurant.id));
    }

    return (
        <>
            <Head
                title={t('storefront.page_title', {
                    restaurant: restaurant.name_en,
                })}
            />
            <AppDirectionProvider>
                <div className="mx-auto max-w-2xl space-y-6 p-6">
                    <header className="flex items-center justify-between gap-4">
                        <div>
                            <h1 className="text-xl font-semibold">
                                {restaurant.name_en}
                            </h1>
                            {restaurant.description_en ? (
                                <p className="text-sm text-muted-foreground">
                                    {restaurant.description_en}
                                </p>
                            ) : null}
                        </div>
                        <LanguageSwitcher />
                    </header>

                    {dishes.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            {t('storefront.no_dishes')}
                        </p>
                    ) : (
                        <div className="space-y-4">
                            {dishes.map((dish) => (
                                <DishSelectorCard
                                    key={dish.id}
                                    dish={dish}
                                    line={lineFor(lines, dish.id)}
                                    onChange={(line) => setLine(dish.id, line)}
                                />
                            ))}
                        </div>
                    )}

                    <Button onClick={handleCheckout} disabled={!hasItems}>
                        {t('storefront.checkout')}
                    </Button>
                </div>
            </AppDirectionProvider>
        </>
    );
}
