import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import DishController from '@/actions/App/Http/Controllers/Menu/DishController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Restaurant = { id: number; name_en: string };
type Option = { id: number; name_en: string };

export default function DishCreate({
    restaurant,
    kitchens,
    categories,
}: {
    restaurant: Restaurant;
    kitchens: Option[];
    categories: Option[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.dishes.create.page_title')} />

            <div className="space-y-6">
                <Heading variant="small" title={t('menu.dishes.create.heading')} />

                <Form {...DishController.store.form(restaurant.id)} className="max-w-xl space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name_en">{t('menu.dishes.fields.name_en')}</Label>
                                <Input id="name_en" name="name_en" required />
                                <InputError message={errors.name_en} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name_ar">{t('menu.dishes.fields.name_ar')}</Label>
                                <Input id="name_ar" name="name_ar" dir="rtl" required />
                                <InputError message={errors.name_ar} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="price">{t('menu.dishes.fields.price')}</Label>
                                <Input id="price" name="price" type="number" step="0.01" min="0" required />
                                <InputError message={errors.price} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="kitchen_id">{t('menu.dishes.fields.kitchen')}</Label>
                                <Select name="kitchen_id" required>
                                    <SelectTrigger id="kitchen_id">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {kitchens.map((kitchen) => (
                                            <SelectItem key={kitchen.id} value={String(kitchen.id)}>
                                                {kitchen.name_en}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.kitchen_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="category_id">{t('menu.dishes.fields.category')}</Label>
                                <Select name="category_id" required>
                                    <SelectTrigger id="category_id">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((category) => (
                                            <SelectItem key={category.id} value={String(category.id)}>
                                                {category.name_en}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.category_id} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('menu.dishes.create.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
