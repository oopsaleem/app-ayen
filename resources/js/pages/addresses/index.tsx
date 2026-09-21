import { Form, Head, Link } from '@inertiajs/react';
import { MapPin, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import DeliveryAddressController from '@/actions/App/Http/Controllers/Addresses/DeliveryAddressController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/addresses';
import type { DeliveryAddress as DeliveryAddressType } from '@/types';

export default function AddressesIndex({
    addresses,
}: {
    addresses: DeliveryAddressType[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('addresses.index.page_title')} />

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title={t('addresses.index.heading')}
                        description={t('addresses.index.description')}
                    />

                    <Button asChild>
                        <Link href={create()}>
                            <Plus /> {t('addresses.index.new_address')}
                        </Link>
                    </Button>
                </div>

                <div className="space-y-3">
                    {addresses.map((address) => (
                        <div
                            key={address.id}
                            className="flex items-center justify-between gap-4 rounded-lg border p-4"
                        >
                            <Link
                                href={edit(address.id)}
                                className="flex items-start gap-3 hover:underline"
                            >
                                <MapPin className="mt-1 size-4 text-muted-foreground" />
                                <span>
                                    <span className="flex items-center gap-2 font-medium">
                                        {address.caption}
                                        {address.is_default ? (
                                            <Badge variant="secondary">
                                                {t('addresses.index.default')}
                                            </Badge>
                                        ) : null}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {address.address}
                                    </span>
                                </span>
                            </Link>

                            <Form {...DeliveryAddressController.destroy.form(address.id)}>
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        size="sm"
                                        disabled={processing}
                                    >
                                        {t('addresses.index.remove')}
                                    </Button>
                                )}
                            </Form>
                        </div>
                    ))}

                    {addresses.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            {t('addresses.index.no_addresses')}
                        </p>
                    ) : null}
                </div>
            </div>
        </>
    );
}

AddressesIndex.layout = {
    breadcrumbs: [{ title: 'addresses.index.page_title', href: index() }],
};
