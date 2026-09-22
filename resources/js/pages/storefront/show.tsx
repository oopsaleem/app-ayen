import { Head } from '@inertiajs/react';
import AppDirectionProvider from '@/components/app-direction-provider';

type OptionOrSize = {
    id: number;
    name_en: string;
    name_ar: string;
    price: string;
};

type Dish = {
    id: number;
    name_en: string;
    name_ar: string;
    description_en: string | null;
    price: string;
    serving_sizes: OptionOrSize[];
    options: OptionOrSize[];
};

type StorefrontRestaurant = {
    id: number;
    name_en: string;
    description_en: string | null;
};

export default function StorefrontShow({
    restaurant,
    dishes,
}: {
    restaurant: StorefrontRestaurant;
    dishes: Dish[];
}) {
    return (
        <>
            <Head title={restaurant.name_en} />
            <AppDirectionProvider>
                <div className="mx-auto max-w-3xl space-y-6 p-6">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold">
                            {restaurant.name_en}
                        </h1>
                        {restaurant.description_en && (
                            <p className="text-muted-foreground">
                                {restaurant.description_en}
                            </p>
                        )}
                    </div>

                    <div className="space-y-4">
                        {dishes.map((dish) => (
                            <div key={dish.id} className="rounded-lg border p-4">
                                <div className="font-medium">{dish.name_en}</div>
                                {dish.description_en && (
                                    <p className="text-sm text-muted-foreground">
                                        {dish.description_en}
                                    </p>
                                )}
                                <div className="mt-1 text-sm">{dish.price}</div>
                            </div>
                        ))}
                    </div>
                </div>
            </AppDirectionProvider>
        </>
    );
}
