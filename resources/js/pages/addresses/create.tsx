import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import DeliveryAddressController from '@/actions/App/Http/Controllers/Addresses/DeliveryAddressController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/addresses';

export default function AddressCreate() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('addresses.create.page_title')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('addresses.create.heading')}
                    description={t('addresses.create.description')}
                />

                <Form {...DeliveryAddressController.store.form()} className="max-w-xl space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="caption">
                                    {t('addresses.fields.caption')}
                                </Label>
                                <Input id="caption" name="caption" required />
                                <InputError message={errors.caption} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="address">
                                    {t('addresses.fields.address')}
                                </Label>
                                <textarea
                                    id="address"
                                    name="address"
                                    rows={3}
                                    required
                                    className="border-input flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-sm outline-none"
                                />
                                <InputError message={errors.address} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="lat">
                                        {t('addresses.fields.lat')}
                                    </Label>
                                    <Input id="lat" name="lat" type="number" step="any" required />
                                    <InputError message={errors.lat} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="lng">
                                        {t('addresses.fields.lng')}
                                    </Label>
                                    <Input id="lng" name="lng" type="number" step="any" required />
                                    <InputError message={errors.lng} />
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox id="is_default" name="is_default" />
                                <Label htmlFor="is_default">
                                    {t('addresses.fields.is_default')}
                                </Label>
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('addresses.create.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AddressCreate.layout = {
    breadcrumbs: [
        { title: 'addresses.index.page_title', href: index() },
        { title: 'addresses.create.page_title', href: index() },
    ],
};
