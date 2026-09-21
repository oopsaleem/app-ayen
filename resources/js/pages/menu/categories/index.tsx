import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { create, edit } from '@/routes/categories';

type Category = {
    id: number;
    parent_id: number | null;
    name_en: string;
    name_ar: string;
    level: number;
};

type Restaurant = { id: number; name_en: string };

export default function CategoriesIndex({
    restaurant,
    categories,
}: {
    restaurant: Restaurant;
    categories: Category[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.categories.index.page_title')} />

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title={t('menu.categories.index.heading', { restaurant: restaurant.name_en })}
                    />

                    <Button asChild>
                        <Link href={create(restaurant.id)}>
                            <Plus /> {t('menu.categories.index.new_category')}
                        </Link>
                    </Button>
                </div>

                <div className="space-y-2">
                    {categories.map((category) => (
                        <Link
                            key={category.id}
                            href={edit(category.id)}
                            className="flex items-center justify-between rounded-lg border p-3 hover:bg-accent"
                            style={{ marginInlineStart: `${category.level * 1.5}rem` }}
                        >
                            <span>{category.name_en}</span>
                            <span dir="rtl" className="text-sm text-muted-foreground">
                                {category.name_ar}
                            </span>
                        </Link>
                    ))}

                    {categories.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            {t('menu.categories.index.no_categories')}
                        </p>
                    ) : null}
                </div>
            </div>
        </>
    );
}
