import { Head } from '@inertiajs/react';
import { ExternalLink, Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DishDialog } from '@/components/dish-dialog';
import type {
    DishDetail,
    ImageLimits,
    NamedOption,
} from '@/components/dish-dialog';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show as storefrontShow } from '@/routes/storefront';

type Dish = DishDetail & {
    kitchen: { name_en: string };
    category: { name_en: string };
};

type Restaurant = { id: number; name_en: string; slug: string };

type DialogState = { open: false } | { open: true; dishId: number | null };

export default function DishesIndex({
    restaurant,
    verified,
    dishes,
    kitchens,
    categories,
    imageLimits,
}: {
    restaurant: Restaurant;
    verified: boolean;
    dishes: Dish[];
    kitchens: NamedOption[];
    categories: NamedOption[];
    imageLimits: ImageLimits;
}) {
    const { t } = useTranslation();
    const [dialog, setDialog] = useState<DialogState>({ open: false });

    // Read the dish from props on every render so row saves inside the
    // dialog show the server's latest data.
    const editingDish =
        dialog.open && dialog.dishId !== null
            ? (dishes.find((dish) => dish.id === dialog.dishId) ?? null)
            : null;

    return (
        <>
            <Head title={t('menu.dishes.index.page_title')} />

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title={t('menu.dishes.index.heading', {
                            restaurant: restaurant.name_en,
                        })}
                    />

                    <div className="flex items-center gap-2">
                        {verified ? (
                            <Button asChild variant="outline">
                                <a
                                    href={storefrontShow.url(restaurant.slug)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ExternalLink />{' '}
                                    {t('menu.dishes.index.public_link')}
                                </a>
                            </Button>
                        ) : null}

                        <Button
                            onClick={() =>
                                setDialog({ open: true, dishId: null })
                            }
                        >
                            <Plus /> {t('menu.dishes.index.new_dish')}
                        </Button>
                    </div>
                </div>

                <div className="space-y-2">
                    {dishes.map((dish) => (
                        <button
                            key={dish.id}
                            type="button"
                            onClick={() =>
                                setDialog({ open: true, dishId: dish.id })
                            }
                            className="flex w-full items-center gap-3 rounded-lg border p-3 text-start hover:bg-accent"
                        >
                            {dish.image_urls[0] ? (
                                <img
                                    src={dish.image_urls[0]}
                                    alt=""
                                    className="size-12 rounded-md object-cover"
                                />
                            ) : null}
                            <div className="flex-1">
                                <div className="font-medium">
                                    {dish.name_en}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {dish.kitchen.name_en} ·{' '}
                                    {dish.category.name_en}
                                </div>
                            </div>
                            {dish.is_available ? null : (
                                <Badge variant="secondary">
                                    {t('menu.dishes.index.unavailable')}
                                </Badge>
                            )}
                            <span>{dish.price}</span>
                        </button>
                    ))}

                    {dishes.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            {t('menu.dishes.index.no_dishes')}
                        </p>
                    ) : null}
                </div>
            </div>

            <DishDialog
                open={dialog.open}
                onOpenChange={(open) =>
                    setDialog(open && dialog.open ? dialog : { open: false })
                }
                dish={editingDish}
                restaurantId={restaurant.id}
                imageLimits={imageLimits}
                kitchens={kitchens}
                categories={categories}
            />
        </>
    );
}
