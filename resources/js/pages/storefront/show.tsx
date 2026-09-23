import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppDirectionProvider from '@/components/app-direction-provider';
import { CartDrawer } from '@/components/menu/cart-drawer';
import { CategorySections } from '@/components/menu/category-section';
import { Hero } from '@/components/menu/hero';
import { Button } from '@/components/ui/button';
import { useOrderCart } from '@/hooks/use-order-cart';
import { writeStoredCart } from '@/lib/order-cart';
import type { Category } from '@/lib/order-cart';
import { order } from '@/routes/restaurants';

type Restaurant = {
    id: number;
    name_en: string;
    description_en?: string | null;
    image_urls: string[];
};

export default function StorefrontShow({
    restaurant,
    categories,
}: {
    restaurant: Restaurant;
    categories: Category[];
}) {
    const { t } = useTranslation();
    const cart = useOrderCart();

    function handleCheckout() {
        writeStoredCart(restaurant.id, cart.items);
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
                <div className="mx-auto max-w-5xl space-y-8 p-6">
                    <Hero restaurant={restaurant} />

                    {categories.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            {t('storefront.no_dishes')}
                        </p>
                    ) : (
                        <CategorySections
                            categories={categories}
                            onAdd={(dish, selection) => cart.add(dish, selection)}
                        />
                    )}
                </div>

                <CartDrawer
                    cart={cart}
                    footer={
                        <Button className="w-full" onClick={handleCheckout}>
                            {t('storefront.checkout')}
                        </Button>
                    }
                />
            </AppDirectionProvider>
        </>
    );
}
