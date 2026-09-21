import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import RestaurantController from '@/actions/App/Http/Controllers/Restaurants/RestaurantController';
import RestaurantVerificationController from '@/actions/App/Http/Controllers/Restaurants/RestaurantVerificationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/restaurants';

type Restaurant = {
    id: number;
    name_en: string;
    name_ar: string;
    description_en: string | null;
    description_ar: string | null;
};

type Address = { address: string; lat: number; lng: number } | undefined;

export default function RestaurantEdit({
    restaurant,
    address,
    verified,
    canVerify,
}: {
    restaurant: Restaurant;
    address: Address;
    verified: boolean;
    canVerify: boolean;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('restaurants.edit.page_title', { name: restaurant.name_en })} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading variant="small" title={t('restaurants.edit.heading')} />
                    <div className="flex items-center gap-2">
                        <Badge variant={verified ? 'default' : 'secondary'}>
                            {t(
                                verified
                                    ? 'restaurants.edit.verified_badge'
                                    : 'restaurants.edit.unverified_badge',
                            )}
                        </Badge>

                        {canVerify ? (
                            <Form {...RestaurantVerificationController.form(restaurant.id)}>
                                {({ processing }) => (
                                    <Button type="submit" variant="outline" size="sm" disabled={processing}>
                                        {t(
                                            verified
                                                ? 'restaurants.edit.unverify'
                                                : 'restaurants.edit.verify',
                                        )}
                                    </Button>
                                )}
                            </Form>
                        ) : null}
                    </div>
                </div>

                <Form
                    {...RestaurantController.update.form(restaurant.id)}
                    options={{ preserveScroll: true }}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name_en">{t('restaurants.fields.name_en')}</Label>
                                <Input id="name_en" name="name_en" defaultValue={restaurant.name_en} required />
                                <InputError message={errors.name_en} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name_ar">{t('restaurants.fields.name_ar')}</Label>
                                <Input
                                    id="name_ar"
                                    name="name_ar"
                                    dir="rtl"
                                    defaultValue={restaurant.name_ar}
                                    required
                                />
                                <InputError message={errors.name_ar} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="address_address">
                                    {t('restaurants.fields.address')}
                                </Label>
                                <Input
                                    id="address_address"
                                    name="address[address]"
                                    defaultValue={address?.address}
                                    required
                                />
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
                                        defaultValue={address?.lat}
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
                                        defaultValue={address?.lng}
                                        required
                                    />
                                    <InputError message={errors['address.lng']} />
                                </div>
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('restaurants.edit.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

RestaurantEdit.layout = {
    breadcrumbs: [{ title: 'restaurants.index.page_title', href: index() }],
};
