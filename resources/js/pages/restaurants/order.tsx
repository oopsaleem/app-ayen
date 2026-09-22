import { Form, Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import OrderController from '@/actions/App/Http/Controllers/Orders/OrderController';
import { DishSelectorCard } from '@/components/dish-selector-card';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { lineFor, readStoredCart } from '@/lib/order-cart';
import type { Dish, Line } from '@/lib/order-cart';
import { index as addressesIndex } from '@/routes/addresses';

type Restaurant = { id: number; name_en: string };

type Address = {
    id: number;
    caption: string;
    address: string;
    is_default: boolean;
};

/**
 * Raw shape submitted by the native form fields (see the `lines[dishId][...]`
 * naming below), before `transform` drops zero-quantity entries and reshapes
 * it into the flat `lines` array the server expects.
 */
type RawLine = {
    quantity?: string;
    serving_size_id?: string;
    option_ids?: string[];
};

export default function RestaurantOrder({
    restaurant,
    dishes,
    addresses,
}: {
    restaurant: Restaurant;
    dishes: Dish[];
    addresses: Address[];
}) {
    const { t } = useTranslation();

    const [restoredCart] = useState(() =>
        readStoredCart(restaurant.id, dishes),
    );
    const [lines, setLines] = useState<Record<number, Line>>(
        () => restoredCart.lines,
    );
    const [deliveryMode, setDeliveryMode] = useState<'delivery' | 'pickup'>(
        'delivery',
    );
    const [deliveryAddressId, setDeliveryAddressId] = useState<
        string | undefined
    >();

    const hasShownRemovedToast = useRef(false);

    useEffect(() => {
        if (restoredCart.removedCount > 0 && !hasShownRemovedToast.current) {
            hasShownRemovedToast.current = true;
            toast.info(t('orders.cart_restored_removed_items'));
        }
    }, [restoredCart.removedCount, t]);

    function setLine(dishId: number, line: Line) {
        setLines((current) => ({ ...current, [dishId]: line }));
    }

    function transform(data: Record<string, unknown>) {
        const rawLines = (data.lines ?? {}) as Record<string, RawLine>;

        return {
            ...data,
            lines: Object.entries(rawLines)
                .filter(([, line]) => Number(line.quantity ?? 0) > 0)
                .map(([dishId, line]) => ({
                    dish_id: Number(dishId),
                    serving_size_id: line.serving_size_id
                        ? Number(line.serving_size_id)
                        : undefined,
                    option_ids: (line.option_ids ?? []).map(Number),
                    quantity: Number(line.quantity),
                })),
            delivery_address_id:
                deliveryMode === 'pickup' ? undefined : deliveryAddressId,
        };
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

                {dishes.length === 0 ? (
                    <p className="py-8 text-center text-muted-foreground">
                        {t('orders.no_dishes')}
                    </p>
                ) : (
                    <Form
                        {...OrderController.store.form()}
                        transform={transform}
                        className="max-w-2xl space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                {dishes.map((dish) => (
                                    <DishSelectorCard
                                        key={dish.id}
                                        dish={dish}
                                        line={lineFor(lines, dish.id)}
                                        onChange={(line) =>
                                            setLine(dish.id, line)
                                        }
                                        fieldName={(suffix) =>
                                            suffix === 'option_ids'
                                                ? `lines[${dish.id}][option_ids][]`
                                                : `lines[${dish.id}][${suffix}]`
                                        }
                                    />
                                ))}

                                <div className="grid gap-2">
                                    <Label htmlFor="delivery_mode">
                                        {t('orders.delivery_mode.label')}
                                    </Label>
                                    <Select
                                        name="delivery_mode"
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
                                        <SelectTrigger
                                            id="delivery_mode"
                                            className="w-full"
                                        >
                                            <SelectValue
                                                placeholder={t(
                                                    'orders.delivery_mode.label',
                                                )}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="delivery">
                                                {t(
                                                    'orders.delivery_mode.delivery',
                                                )}
                                            </SelectItem>
                                            <SelectItem value="pickup">
                                                {t(
                                                    'orders.delivery_mode.pickup',
                                                )}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors['delivery_mode']}
                                    />
                                </div>

                                {deliveryMode === 'delivery' ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="delivery_address_id">
                                            {t('orders.address.label')}
                                        </Label>
                                        <Select
                                            name="delivery_address_id"
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
                                                        value={String(
                                                            address.id,
                                                        )}
                                                    >
                                                        {address.caption} —{' '}
                                                        {address.address}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={
                                                errors['delivery_address_id']
                                            }
                                        />
                                        <Button
                                            asChild
                                            variant="link"
                                            className="px-0"
                                        >
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
                                    type="submit"
                                    disabled={
                                        processing ||
                                        Object.values(lines).every(
                                            (line) => line.quantity === 0,
                                        )
                                    }
                                >
                                    {t('orders.submit')}
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

RestaurantOrder.layout = {
    breadcrumbs: [
        { title: 'restaurants.index.page_title', href: '/restaurants' },
        { title: 'orders.page_title', href: '' },
    ],
};
