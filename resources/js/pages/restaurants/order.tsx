import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import OrderController from '@/actions/App/Http/Controllers/Orders/OrderController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as addressesIndex } from '@/routes/addresses';

type ServingSize = {
    id: number;
    name_en: string;
    name_ar: string;
    price: string;
};

type DishOption = {
    id: number;
    name_en: string;
    name_ar: string;
    price: string;
};

type Dish = {
    id: number;
    name_en: string;
    name_ar: string;
    description_en?: string | null;
    price: string;
    serving_sizes: ServingSize[];
    options: DishOption[];
};

type Restaurant = { id: number; name_en: string };

type Address = {
    id: number;
    caption: string;
    address: string;
    is_default: boolean;
};

type Line = {
    quantity: number;
    serving_size_id?: number;
    option_ids: number[];
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

    const [lines, setLines] = useState<Record<number, Line>>({});
    const [deliveryMode, setDeliveryMode] = useState<'delivery' | 'pickup'>(
        'delivery',
    );
    const [deliveryAddressId, setDeliveryAddressId] = useState<
        string | undefined
    >();

    function lineFor(dishId: number): Line {
        return lines[dishId] ?? { quantity: 0, option_ids: [] };
    }

    function setLine(dishId: number, line: Line) {
        setLines((current) => ({ ...current, [dishId]: line }));
    }

    function unitPriceFor(dish: Dish, line: Line): number {
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
                                {dishes.map((dish) => {
                                    const line = lineFor(dish.id);

                                    return (
                                        <div
                                            key={dish.id}
                                            className="rounded-lg border p-4"
                                        >
                                            <div className="flex items-center justify-between gap-4">
                                                <div>
                                                    <div className="font-medium">
                                                        {dish.name_en}
                                                    </div>
                                                    {dish.description_en ? (
                                                        <div className="text-sm text-muted-foreground">
                                                            {
                                                                dish.description_en
                                                            }
                                                        </div>
                                                    ) : null}
                                                    <div className="text-sm">
                                                        {unitPriceFor(
                                                            dish,
                                                            line,
                                                        )}
                                                    </div>
                                                </div>

                                                <div className="w-24">
                                                    <Label
                                                        htmlFor={`quantity-${dish.id}`}
                                                    >
                                                        {t('orders.quantity')}
                                                    </Label>
                                                    <Input
                                                        id={`quantity-${dish.id}`}
                                                        name={`lines[${dish.id}][quantity]`}
                                                        type="number"
                                                        min={0}
                                                        max={99}
                                                        value={line.quantity}
                                                        onChange={(e) =>
                                                            setLine(dish.id, {
                                                                ...line,
                                                                quantity:
                                                                    Number(
                                                                        e.target
                                                                            .value,
                                                                    ),
                                                            })
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            {dish.serving_sizes.length > 0 ? (
                                                <div className="mt-3">
                                                    <Label>
                                                        {t(
                                                            'orders.serving_size',
                                                        )}
                                                    </Label>
                                                    <Select
                                                        name={`lines[${dish.id}][serving_size_id]`}
                                                        value={
                                                            line.serving_size_id?.toString() ??
                                                            ''
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            setLine(dish.id, {
                                                                ...line,
                                                                serving_size_id:
                                                                    value
                                                                        ? Number(
                                                                              value,
                                                                          )
                                                                        : undefined,
                                                            })
                                                        }
                                                    >
                                                        <SelectTrigger className="mt-1 w-full">
                                                            <SelectValue
                                                                placeholder={t(
                                                                    'orders.serving_size',
                                                                )}
                                                            />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {dish.serving_sizes.map(
                                                                (size) => (
                                                                    <SelectItem
                                                                        key={
                                                                            size.id
                                                                        }
                                                                        value={String(
                                                                            size.id,
                                                                        )}
                                                                    >
                                                                        {
                                                                            size.name_en
                                                                        }{' '}
                                                                        (
                                                                        {
                                                                            size.price
                                                                        }
                                                                        )
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            ) : null}

                                            {dish.options.length > 0 ? (
                                                <div className="mt-3">
                                                    <Label>
                                                        {t('orders.options')}
                                                    </Label>
                                                    <div className="mt-2 grid gap-2">
                                                        {dish.options.map(
                                                            (option) => (
                                                                <label
                                                                    key={
                                                                        option.id
                                                                    }
                                                                    className="flex items-center gap-2 text-sm"
                                                                >
                                                                    <Checkbox
                                                                        name={`lines[${dish.id}][option_ids][]`}
                                                                        value={String(
                                                                            option.id,
                                                                        )}
                                                                        checked={line.option_ids.includes(
                                                                            option.id,
                                                                        )}
                                                                        onCheckedChange={(
                                                                            checked,
                                                                        ) =>
                                                                            setLine(
                                                                                dish.id,
                                                                                {
                                                                                    ...line,
                                                                                    option_ids:
                                                                                        checked ===
                                                                                        true
                                                                                            ? [
                                                                                                  ...line.option_ids,
                                                                                                  option.id,
                                                                                              ]
                                                                                            : line.option_ids.filter(
                                                                                                  (
                                                                                                      id,
                                                                                                  ) =>
                                                                                                      id !==
                                                                                                      option.id,
                                                                                              ),
                                                                                },
                                                                            )
                                                                        }
                                                                    />
                                                                    {
                                                                        option.name_en
                                                                    }{' '}
                                                                    (
                                                                    {
                                                                        option.price
                                                                    }
                                                                    )
                                                                </label>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            ) : null}

                                            {line.quantity > 0 ? (
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    {t('orders.in_order')} ×{' '}
                                                    {line.quantity}
                                                </p>
                                            ) : null}
                                        </div>
                                    );
                                })}

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
