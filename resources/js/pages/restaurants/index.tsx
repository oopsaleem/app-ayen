import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { edit, index } from '@/routes/restaurants';

type Restaurant = {
    id: number;
    company_id: number;
    name_en: string;
    name_ar: string;
};

export default function RestaurantsIndex({ restaurants }: { restaurants: Restaurant[] }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('restaurants.index.page_title')} />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('restaurants.index.heading')}
                    description={t('restaurants.index.description')}
                />

                <div className="space-y-3">
                    {restaurants.map((restaurant) => (
                        <Link
                            key={restaurant.id}
                            href={edit(restaurant.id)}
                            className="flex items-center justify-between gap-4 rounded-lg border p-4 hover:bg-accent"
                        >
                            <span className="font-medium">{restaurant.name_en}</span>
                            <span dir="rtl" className="text-sm text-muted-foreground">
                                {restaurant.name_ar}
                            </span>
                        </Link>
                    ))}

                    {restaurants.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            {t('restaurants.index.no_restaurants')}
                        </p>
                    ) : null}
                </div>
            </div>
        </>
    );
}

RestaurantsIndex.layout = {
    breadcrumbs: [{ title: 'restaurants.index.page_title', href: index() }],
};
