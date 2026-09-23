import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import OrderController from '@/actions/App/Http/Controllers/Orders/OrderController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { CartDrawer } from '@/components/menu/cart-drawer';
import { CategorySections } from '@/components/menu/category-section';
import { Hero } from '@/components/menu/hero';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useOrderCart } from '@/hooks/use-order-cart';
import { readStoredCart } from '@/lib/order-cart';
import type { Category } from '@/lib/order-cart';
import { index as addressesIndex } from '@/routes/addresses';

type Restaurant = {
    id: number;
    name_en: string;
    description_en?: string | null;
    image_urls: string[];
};

type Address = {
    id: number;
    caption: string;
    address: string;
    is_default: boolean;
};

export default function RestaurantOrder({
    restaurant,
    categories,
    addresses,
}: {
    restaurant: Restaurant;
    categories: Category[];
    addresses: Address[];
}) {
    const { t } = useTranslation();

    const [restoredCart] = useState(() =>
        readStoredCart(restaurant.id, categories),
    );
    const cart = useOrderCart(restoredCart.items);
    const [deliveryMode, setDeliveryMode] = useState<'delivery' | 'pickup'>(
        'delivery',
    );
    const [deliveryAddressId, setDeliveryAddressId] = useState<
        string | undefined
    >();
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const hasShownRemovedToast = useRef(false);

    useEffect(() => {
        if (restoredCart.removedCount > 0 && !hasShownRemovedToast.current) {
            hasShownRemovedToast.current = true;
            toast.info(t('orders.cart_restored_removed_items'));
        }
    }, [restoredCart.removedCount, t]);

    function submit() {
        router.post(
            OrderController.store.url(),
            {
                delivery_mode: deliveryMode,
                delivery_address_id:
                    deliveryMode === 'pickup' ? undefined : deliveryAddressId,
                lines: cart.items.map((item) => ({
                    dish_id: item.dish.id,
                    serving_size_id: item.serving_size_id,
                    option_ids: item.option_ids,
                    quantity: item.quantity,
                })),
            },
            {
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (formErrors) =>
                    setErrors(formErrors as Record<string, string>),
            },
        );
    }

    return (
        <>
            <Head title={t('orders.page_title')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('orders.heading')}
                    description={restaurant.name_en}
                />

                <Hero restaurant={restaurant} />

                {categories.length === 0 ? (
                    <p className="py-8 text-center text-muted-foreground">
                        {t('orders.no_dishes')}
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
                    <div className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="delivery_mode">
                                {t('orders.delivery_mode.label')}
                            </Label>
                            <Select
                                value={deliveryMode}
                                onValueChange={(value) => {
                                    setDeliveryMode(
                                        value as 'delivery' | 'pickup',
                                    );

                                    if (value === 'pickup') {
                                        setDeliveryAddressId(undefined);
                                    }
                                }}
                            >
                                <SelectTrigger id="delivery_mode" className="w-full">
                                    <SelectValue
                                        placeholder={t(
                                            'orders.delivery_mode.label',
                                        )}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="delivery">
                                        {t('orders.delivery_mode.delivery')}
                                    </SelectItem>
                                    <SelectItem value="pickup">
                                        {t('orders.delivery_mode.pickup')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors['delivery_mode']} />
                        </div>

                        {deliveryMode === 'delivery' ? (
                            <div className="grid gap-2">
                                <Label htmlFor="delivery_address_id">
                                    {t('orders.address.label')}
                                </Label>
                                <Select
                                    value={deliveryAddressId}
                                    onValueChange={setDeliveryAddressId}
                                >
                                    <SelectTrigger
                                        id="delivery_address_id"
                                        className="w-full"
                                    >
                                        <SelectValue
                                            placeholder={t(
                                                'orders.address.label',
                                            )}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {addresses.map((address) => (
                                            <SelectItem
                                                key={address.id}
                                                value={String(address.id)}
                                            >
                                                {address.caption} —{' '}
                                                {address.address}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={errors['delivery_address_id']}
                                />
                                <Button asChild variant="link" className="px-0">
                                    <a href={addressesIndex().url}>
                                        {t('orders.address.empty')}
                                    </a>
                                </Button>
                            </div>
                        ) : null}

                        {errors['lines'] ? (
                            <InputError message={errors['lines']} />
                        ) : null}

                        <Button
                            className="w-full"
                            disabled={
                                processing ||
                                cart.items.length === 0 ||
                                (deliveryMode === 'delivery' &&
                                    !deliveryAddressId)
                            }
                            onClick={submit}
                        >
                            {t('orders.submit')}
                        </Button>
                    </div>
                }
            />
        </>
    );
}

RestaurantOrder.layout = {
    breadcrumbs: [
        { title: 'restaurants.index.page_title', href: '/restaurants' },
        { title: 'orders.page_title', href: '' },
    ],
};
