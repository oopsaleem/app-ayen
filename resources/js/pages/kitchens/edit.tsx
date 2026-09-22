import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import KitchenController from '@/actions/App/Http/Controllers/Kitchens/KitchenController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/restaurants';

type Kitchen = {
    id: number;
    restaurant_id: number;
    name_en: string;
    name_ar: string;
};

export default function KitchenEdit({ kitchen }: { kitchen: Kitchen }) {
    const { t } = useTranslation();

    return (
        <>
            <Head
                title={t('kitchens.edit.page_title', { name: kitchen.name_en })}
            />

            <div className="space-y-6">
                <Heading variant="small" title={t('kitchens.edit.heading')} />

                <Form
                    {...KitchenController.update.form(kitchen.id)}
                    options={{ preserveScroll: true }}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name_en">
                                    {t('kitchens.fields.name_en')}
                                </Label>
                                <Input
                                    id="name_en"
                                    name="name_en"
                                    defaultValue={kitchen.name_en}
                                    required
                                />
                                <InputError message={errors.name_en} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name_ar">
                                    {t('kitchens.fields.name_ar')}
                                </Label>
                                <Input
                                    id="name_ar"
                                    name="name_ar"
                                    dir="rtl"
                                    defaultValue={kitchen.name_ar}
                                    required
                                />
                                <InputError message={errors.name_ar} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('kitchens.edit.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

KitchenEdit.layout = {
    breadcrumbs: [{ title: 'restaurants.index.page_title', href: index() }],
};
