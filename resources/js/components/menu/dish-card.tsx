import { ShoppingCart, SlidersHorizontal, Users } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useLocale } from '@/hooks/use-locale';
import { unitPriceFor } from '@/lib/order-cart';
import type { CartSelection, Dish } from '@/lib/order-cart';

export function DishCard({
    dish,
    onAdd,
}: {
    dish: Dish;
    onAdd: (selection: CartSelection) => void;
}) {
    const { t } = useTranslation();
    const { locale } = useLocale();
    const [open, setOpen] = useState(false);
    const defaultServingSize =
        dish.serving_sizes.find((size) => size.is_default) ??
        dish.serving_sizes[0];
    const [servingSizeId, setServingSizeId] = useState(defaultServingSize?.id);
    const [optionIds, setOptionIds] = useState<number[]>([]);

    const name = locale === 'ar' ? dish.name_ar : dish.name_en;
    const description =
        locale === 'ar' ? dish.description_ar : dish.description_en;
    const selectedServingSize = dish.serving_sizes.find(
        (size) => size.id === servingSizeId,
    );
    const canCustomize = dish.serving_sizes.length > 0 || dish.options.length > 0;
    const selection: CartSelection = {
        serving_size_id: servingSizeId,
        option_ids: optionIds,
    };

    const toggleOption = (optionId: number) => {
        setOptionIds((current) =>
            current.includes(optionId)
                ? current.filter((id) => id !== optionId)
                : [...current, optionId],
        );
    };

    const addToCart = () => {
        onAdd(selection);
        setOpen(false);
        setOptionIds([]);
        setServingSizeId(defaultServingSize?.id);
    };

    return (
        <>
            <Card className="flex flex-col overflow-hidden">
                {dish.image_urls[0] ? (
                    <div className="aspect-video w-full overflow-hidden bg-muted">
                        <img
                            src={dish.image_urls[0]}
                            alt=""
                            className="size-full object-cover"
                        />
                    </div>
                ) : null}

                <CardContent className="flex-1 space-y-2">
                    <div className="flex items-start justify-between gap-2">
                        <h3 className="font-medium">{name}</h3>
                        {selectedServingSize ? (
                            <Badge
                                variant="secondary"
                                className="shrink-0 gap-1"
                            >
                                <Users className="size-3" />
                                {selectedServingSize.servings_count}
                            </Badge>
                        ) : null}
                    </div>

                    {description ? (
                        <p className="line-clamp-2 text-sm text-muted-foreground">
                            {description}
                        </p>
                    ) : null}
                </CardContent>

                <CardFooter className="items-center justify-between border-t pt-4">
                    <span className="text-lg font-semibold">
                        {unitPriceFor(dish, selection)}
                    </span>

                    <div className="flex gap-2">
                        {canCustomize ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label={t('menu.browse.dish_card.customize')}
                                onClick={() => setOpen(true)}
                            >
                                <SlidersHorizontal />
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            size="icon"
                            aria-label={t('menu.browse.dish_card.add_to_cart')}
                            onClick={addToCart}
                        >
                            <ShoppingCart />
                        </Button>
                    </div>
                </CardFooter>
            </Card>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{name}</DialogTitle>
                    </DialogHeader>

                    <div className="space-y-6">
                        {dish.serving_sizes.length > 0 ? (
                            <div className="space-y-2">
                                <p className="text-sm font-medium">
                                    {t('menu.browse.customize.select_size')}
                                </p>
                                <div className="grid gap-2">
                                    {dish.serving_sizes.map((size) => (
                                        <Button
                                            key={size.id}
                                            type="button"
                                            variant={
                                                servingSizeId === size.id
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            className="w-full justify-between"
                                            onClick={() =>
                                                setServingSizeId(size.id)
                                            }
                                        >
                                            <span>
                                                {locale === 'ar'
                                                    ? size.name_ar
                                                    : size.name_en}
                                            </span>
                                            <span>{size.price}</span>
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        ) : null}

                        {dish.options.length > 0 ? (
                            <div className="space-y-2">
                                <p className="text-sm font-medium">
                                    {t('menu.browse.customize.addons')}
                                </p>
                                <div className="space-y-2">
                                    {dish.options.map((option) => (
                                        <label
                                            key={option.id}
                                            className="flex cursor-pointer items-center justify-between rounded-lg border p-3 text-sm hover:bg-accent"
                                        >
                                            <span className="flex items-center gap-3">
                                                <Checkbox
                                                    checked={optionIds.includes(
                                                        option.id,
                                                    )}
                                                    onCheckedChange={() =>
                                                        toggleOption(option.id)
                                                    }
                                                />
                                                {locale === 'ar'
                                                    ? option.name_ar
                                                    : option.name_en}
                                            </span>
                                            <span className="font-medium">
                                                {option.price}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        ) : null}

                        <div className="flex items-center justify-between border-t pt-4">
                            <span className="font-medium">
                                {t('menu.browse.customize.total')}
                            </span>
                            <span className="text-lg font-semibold">
                                {unitPriceFor(dish, selection)}
                            </span>
                        </div>

                        <Button className="w-full" onClick={addToCart}>
                            {t('menu.browse.customize.add_to_cart')}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

export default DishCard;
