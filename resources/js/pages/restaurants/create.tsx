import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import RestaurantController from '@/actions/App/Http/Controllers/Restaurants/RestaurantController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/restaurants';

type Company = { id: number; display_name: string };

export default function RestaurantCreate({ company }: { company: Company }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('restaurants.create.page_title')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('restaurants.create.heading', { company: company.display_name })}
                />

                <Form
                    {...RestaurantController.store.form(company.id)}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name_en">{t('restaurants.fields.name_en')}</Label>
                                <Input id="name_en" name="name_en" required />
                                <InputError message={errors.name_en} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name_ar">{t('restaurants.fields.name_ar')}</Label>
                                <Input id="name_ar" name="name_ar" dir="rtl" required />
                                <InputError message={errors.name_ar} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="address_address">
                                    {t('restaurants.fields.address')}
                                </Label>
                                <Input id="address_address" name="address[address]" required />
                                <InputError message={errors['address.address']} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="address_lat">{t('restaurants.fields.lat')}</Label>
                                    <Input
                                        id="address_lat"
                                        name="address[lat]"
                                        type="number"
                                        step="any"
                                        required
                                    />
                                    <InputError message={errors['address.lat']} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="address_lng">{t('restaurants.fields.lng')}</Label>
                                    <Input
                                        id="address_lng"
                                        name="address[lng]"
                                        type="number"
                                        step="any"
                                        required
                                    />
                                    <InputError message={errors['address.lng']} />
                                </div>
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('restaurants.create.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

RestaurantCreate.layout = {
    breadcrumbs: [{ title: 'restaurants.index.page_title', href: index() }],
};
