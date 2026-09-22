import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { create, edit } from '@/routes/dishes';

type Dish = {
    id: number;
    name_en: string;
    name_ar: string;
    price: number;
    kitchen: { name_en: string };
    category: { name_en: string };
};

type Restaurant = { id: number; name_en: string };

export default function DishesIndex({
    restaurant,
    dishes,
}: {
    restaurant: Restaurant;
    dishes: Dish[];
}) {
    const { t } = useTranslation();

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

                    <Button asChild>
                        <Link href={create(restaurant.id)}>
                            <Plus /> {t('menu.dishes.index.new_dish')}
                        </Link>
                    </Button>
                </div>

                <div className="space-y-2">
                    {dishes.map((dish) => (
                        <Link
                            key={dish.id}
                            href={edit(dish.id)}
                            className="flex items-center justify-between rounded-lg border p-3 hover:bg-accent"
                        >
                            <div>
                                <div className="font-medium">
                                    {dish.name_en}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {dish.kitchen.name_en} ·{' '}
                                    {dish.category.name_en}
                                </div>
                            </div>
                            <span>{dish.price}</span>
                        </Link>
                    ))}

                    {dishes.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            {t('menu.dishes.index.no_dishes')}
                        </p>
                    ) : null}
                </div>
            </div>
        </>
    );
}
