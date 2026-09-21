import { Head, useForm } from '@inertiajs/react';
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
    dish_id: number;
    serving_size_id?: number;
    option_ids: number[];
    quantity: number;
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

    const { data, setData, post, processing, errors, reset } = useForm<{
        lines: Line[];
        delivery_mode: 'delivery' | 'pickup';
        delivery_address_id?: string;
    }>({
        lines: [],
        delivery_mode: 'delivery',
        delivery_address_id: undefined,
    });

    function unitPriceFor(dish: Dish, line?: Line): number {
        const selectedSize = dish.serving_sizes.find(
            (size) => size.id === line?.serving_size_id,
        );
        const base = Number(selectedSize?.price ?? dish.price);
        const extras = line?.option_ids.reduce(
            (sum, id) =>
                sum +
                Number(
                    dish.options.find((option) => option.id === id)?.price ?? 0,
                ),
            0,
        );

        return Number((base + (extras ?? 0)).toFixed(2));
    }

    const lineByDish = (dishId: number) =>
        data.lines.find((line) => line.dish_id === dishId);

    function upsertLine(line: Line) {
        setData(
            'lines',
            data.lines.some((l) => l.dish_id === line.dish_id)
                ? data.lines.map((l) => (l.dish_id === line.dish_id ? line : l))
                : [...data.lines, line],
        );
    }

    function removeLine(dishId: number) {
        setData(
            'lines',
            data.lines.filter((line) => line.dish_id !== dishId),
        );
    }

    function setServingSize(dishId: number, servingSizeId?: number) {
        const line = lineByDish(dishId);

        if (line) {
            upsertLine({ ...line, serving_size_id: servingSizeId });
        }
    }

    function toggleOption(dishId: number, optionId: number, checked: boolean) {
        const line = lineByDish(dishId);

        if (!line) {
            return;
        }

        if (checked) {
            upsertLine({ ...line, option_ids: [...line.option_ids, optionId] });
        } else {
            upsertLine({
                ...line,
                option_ids: line.option_ids.filter((id) => id !== optionId),
            });
        }
    }

    function setQuantity(dishId: number, quantity: number) {
        if (quantity > 0) {
            const line = lineByDish(dishId) ?? {
                dish_id: dishId,
                option_ids: [],
            };
            upsertLine({ ...line, quantity });
        } else {
            removeLine(dishId);
        }
    }

    function submit() {
        post(OrderController.store.url(), {
            onSuccess: () => reset(),
        });
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
                    <div className="max-w-2xl space-y-4">
                        {dishes.map((dish) => {
                            const line = lineByDish(dish.id);
                            const quantity = line?.quantity ?? 0;

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
                                                    {dish.description_en}
                                                </div>
                                            ) : null}
                                            <div className="text-sm">
                                                {unitPriceFor(dish, line)}
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
                                                type="number"
                                                min={0}
                                                max={99}
                                                defaultValue={0}
                                                onChange={(e) =>
                                                    setQuantity(
                                                        dish.id,
                                                        Number(e.target.value),
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>

                                    {dish.serving_sizes.length > 0 ? (
                                        <div className="mt-3">
                                            <Label>
                                                {t('orders.serving_size')}
                                            </Label>
                                            <Select
                                                value={
                                                    line?.serving_size_id?.toString() ??
                                                    ''
                                                }
                                                onValueChange={(value) =>
                                                    setServingSize(
                                                        dish.id,
                                                        Number(value),
                                                    )
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
                                                                key={size.id}
                                                                value={String(
                                                                    size.id,
                                                                )}
                                                            >
                                                                {size.name_en} (
                                                                {size.price})
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    ) : null}

                                    {dish.options.length > 0 ? (
                                        <div className="mt-3">
                                            <Label>{t('orders.options')}</Label>
                                            <div className="mt-2 grid gap-2">
                                                {dish.options.map((option) => (
                                                    <label
                                                        key={option.id}
                                                        className="flex items-center gap-2 text-sm"
                                                    >
                                                        <Checkbox
                                                            checked={line?.option_ids.includes(
                                                                option.id,
                                                            )}
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                toggleOption(
                                                                    dish.id,
                                                                    option.id,
                                                                    checked ===
                                                                        true,
                                                                )
                                                            }
                                                        />
                                                        {option.name_en} (
                                                        {option.price})
                                                    </label>
                                                ))}
                                            </div>
                                        </div>
                                    ) : null}

                                    {quantity > 0 ? (
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {t('orders.in_order')} × {quantity}
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
                                value={data.delivery_mode}
                                onValueChange={(value) =>
                                    setData({
                                        ...data,
                                        delivery_mode: value as
                                            'delivery' | 'pickup',
                                        delivery_address_id:
                                            value === 'delivery'
                                                ? data.delivery_address_id
                                                : undefined,
                                    })
                                }
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
                                        {t('orders.delivery_mode.delivery')}
                                    </SelectItem>
                                    <SelectItem value="pickup">
                                        {t('orders.delivery_mode.pickup')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors['delivery_mode']} />
                        </div>

                        {data.delivery_mode === 'delivery' ? (
                            <div className="grid gap-2">
                                <Label htmlFor="delivery_address_id">
                                    {t('orders.address.label')}
                                </Label>
                                <Select
                                    value={data.delivery_address_id}
                                    onValueChange={(value) =>
                                        setData('delivery_address_id', value)
                                    }
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
                            type="button"
                            disabled={processing || data.lines.length === 0}
                            onClick={submit}
                        >
                            {t('orders.submit')}
                        </Button>
                    </div>
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
