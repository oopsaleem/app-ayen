import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';

type Dish = { id: number; name_en: string; name_ar: string; price: number };
type Kitchen = { id: number; name_en: string; name_ar: string; dishes: Dish[] };

export default function ChefKitchens({ kitchens }: { kitchens: Kitchen[] }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('chef.kitchens.page_title')} />

            <div className="space-y-6">
                <Heading variant="small" title={t('chef.kitchens.heading')} />

                {kitchens.length === 0 ? (
                    <p className="py-8 text-center text-muted-foreground">
                        {t('chef.kitchens.no_kitchens')}
                    </p>
                ) : (
                    <div className="space-y-6">
                        {kitchens.map((kitchen) => (
                            <div key={kitchen.id} className="space-y-2 rounded-lg border p-4">
                                <div className="font-medium">{kitchen.name_en}</div>

                                {kitchen.dishes.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('chef.kitchens.no_dishes')}
                                    </p>
                                ) : (
                                    <ul className="space-y-1">
                                        {kitchen.dishes.map((dish) => (
                                            <li key={dish.id} className="flex justify-between text-sm">
                                                <span>{dish.name_en}</span>
                                                <span>{dish.price}</span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
