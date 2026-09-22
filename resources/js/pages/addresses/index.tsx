import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import DeliveryAddressController from '@/actions/App/Http/Controllers/Addresses/DeliveryAddressController';
import DeliveryAddressCard from '@/components/delivery-addresses/delivery-address-card';
import DeliveryAddressDialog from '@/components/delivery-addresses/delivery-address-dialog';
import Heading from '@/components/heading';
import type { AddressFormData } from '@/components/shared-address-form';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/addresses';
import type { DeliveryAddress as DeliveryAddressType } from '@/types';

export default function AddressesIndex({
    addresses,
}: {
    addresses: DeliveryAddressType[];
}) {
    const { t } = useTranslation();
    const [editingAddress, setEditingAddress] =
        useState<DeliveryAddressType | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    const handleEdit = (address: DeliveryAddressType) => {
        setEditingAddress(address);
        setIsDialogOpen(true);
    };

    const handleDelete = (id: number) => {
        router.delete(DeliveryAddressController.destroy.url(id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('addresses.toast.delete_success'));
            },
            onError: () => {
                toast.error(t('addresses.toast.delete_error'));
            },
        });
    };

    const handleSubmit = (data: AddressFormData) => {
        if (data.id) {
            router.patch(
                DeliveryAddressController.update.url(data.id),
                {
                    caption: data.caption ?? '',
                    address: data.address,
                    lat: data.lat,
                    lng: data.lng,
                    is_default: data.is_default,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setIsDialogOpen(false);
                        setEditingAddress(null);
                        toast.success(t('addresses.toast.update_success'));
                    },
                    onError: (errors) => {
                        toast.error(
                            Object.values(errors)[0] ??
                                t('addresses.toast.update_error'),
                        );
                    },
                },
            );
        } else {
            router.post(
                DeliveryAddressController.store.url(),
                {
                    caption: data.caption ?? '',
                    address: data.address,
                    lat: data.lat,
                    lng: data.lng,
                    is_default: data.is_default,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setIsDialogOpen(false);
                        setEditingAddress(null);
                        toast.success(t('addresses.toast.create_success'));
                    },
                    onError: (errors) => {
                        toast.error(
                            Object.values(errors)[0] ??
                                t('addresses.toast.create_error'),
                        );
                    },
                },
            );
        }
    };

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

                    <Button
                        variant="secondary"
                        onClick={() => {
                            setEditingAddress(null);
                            setIsDialogOpen(true);
                        }}
                    >
                        <Plus /> {t('addresses.index.new_address')}
                    </Button>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {addresses.map((address) => (
                        <DeliveryAddressCard
                            key={address.id}
                            address={address}
                            onEdit={handleEdit}
                            onDelete={handleDelete}
                        />
                    ))}
                </div>

                {addresses.length === 0 ? (
                    <p className="py-8 text-center text-muted-foreground">
                        {t('addresses.index.no_addresses')}
                    </p>
                ) : null}
            </div>

            <DeliveryAddressDialog
                open={isDialogOpen}
                onOpenChange={setIsDialogOpen}
                address={editingAddress}
                onSubmit={handleSubmit}
            />
        </>
    );
}

AddressesIndex.layout = {
    breadcrumbs: [{ title: 'addresses.index.page_title', href: index() }],
};
