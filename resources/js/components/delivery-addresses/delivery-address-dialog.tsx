import { useTranslation } from 'react-i18next';
import SharedAddressForm from '@/components/shared-address-form';
import type { AddressFormData } from '@/components/shared-address-form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { DeliveryAddress } from '@/types';

interface DeliveryAddressDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    address?: DeliveryAddress | null;
    onSubmit: (data: AddressFormData) => Promise<void> | void;
}

export function DeliveryAddressDialog({
    open,
    onOpenChange,
    address,
    onSubmit,
}: DeliveryAddressDialogProps) {
    const { t } = useTranslation();

    const initialData = address
        ? {
              id: address.id,
              caption: address.caption || '',
              address: address.address,
              lat: Number(address.lat),
              lng: Number(address.lng),
              is_default: address.is_default,
          }
        : undefined;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[600px]">
                <DialogHeader>
                    <DialogTitle>
                        {address
                            ? t('addresses.dialog.edit_address')
                            : t('addresses.dialog.add_new_address')}
                    </DialogTitle>
                </DialogHeader>

                <SharedAddressForm
                    initialData={initialData}
                    onSubmit={onSubmit}
                >
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('addresses.dialog.cancel')}
                        </Button>
                        <Button type="submit">
                            {t('addresses.dialog.save')}
                        </Button>
                    </div>
                </SharedAddressForm>
            </DialogContent>
        </Dialog>
    );
}

export default DeliveryAddressDialog;
