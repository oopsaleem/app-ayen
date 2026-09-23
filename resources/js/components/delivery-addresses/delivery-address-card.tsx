import { Check, Copy, Edit2, MapPin, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import MapLink from '@/components/map-link';
import StaticMapSimple from '@/components/static-map-simple';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import type { DeliveryAddress } from '@/types';

interface DeliveryAddressCardProps {
    address: DeliveryAddress;
    onEdit: (address: DeliveryAddress) => void;
    onDelete: (id: number) => void;
}

export function DeliveryAddressCard({
    address,
    onEdit,
    onDelete,
}: DeliveryAddressCardProps) {
    const { t } = useTranslation();
    const lat = Number(address.lat) || 0;
    const lng = Number(address.lng) || 0;
    const [copied, setCopied] = useState(false);

    const handleCopyAddress = () => {
        navigator.clipboard.writeText(address.address);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <Card className="group relative transition-all duration-200 hover:shadow-md">
            <CardContent className="p-4">
                {address.is_default && (
                    <Badge
                        variant="secondary"
                        className="absolute inset-e-2 -top-3 z-10"
                    >
                        {t('addresses.index.default')}
                    </Badge>
                )}

                <div className="space-y-4">
                    <div className="relative overflow-hidden rounded-lg">
                        <MapLink waypoints={[{ lat, lng }]}>
                            <div className="group/map transition-transform duration-200 hover:scale-105">
                                <StaticMapSimple
                                    position={{ lat, lng }}
                                    className="aspect-video h-full w-full rounded-lg"
                                />
                                <div className="absolute inset-0 bg-black/0 transition-all duration-200 group-hover/map:bg-black/10" />
                            </div>
                        </MapLink>
                    </div>

                    <div className="flex items-start gap-3">
                        <MapPin className="mt-1 size-5 shrink-0 text-primary" />
                        <div className="flex-1 space-y-1">
                            {address.caption && (
                                <h3 className="text-lg font-medium">
                                    {address.caption}
                                </h3>
                            )}
                            <p className="text-sm text-muted-foreground">
                                {address.address}
                            </p>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="mt-2 h-8 text-xs"
                                onClick={handleCopyAddress}
                            >
                                {copied ? (
                                    <Check className="mr-1 size-3" />
                                ) : (
                                    <Copy className="mr-1 size-3" />
                                )}
                                {copied
                                    ? t('addresses.card.copied')
                                    : t('addresses.card.copy_address')}
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>

            <CardFooter className="px-4 pb-4">
                <div className="flex w-full justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onEdit(address)}
                        className="flex-1 sm:flex-none"
                    >
                        <Edit2 className="mr-2 size-4" />
                        {t('addresses.card.edit')}
                    </Button>

                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button
                                variant="destructive"
                                size="sm"
                                className="flex-1 sm:flex-none"
                            >
                                <Trash2 className="mr-2 size-4" />
                                {t('addresses.card.delete')}
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader className="place-items-center">
                                <AlertDialogTitle>
                                    {t('addresses.card.delete_confirm.title')}
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    {t(
                                        'addresses.card.delete_confirm.description',
                                    )}
                                </AlertDialogDescription>
                                <AlertDialogFooter className="flex flex-row items-end justify-between">
                                    <AlertDialogCancel>
                                        {t(
                                            'addresses.card.delete_confirm.cancel',
                                        )}
                                    </AlertDialogCancel>
                                    <AlertDialogAction
                                        className="bg-destructive text-white hover:bg-destructive/90"
                                        onClick={() => onDelete(address.id)}
                                    >
                                        {t(
                                            'addresses.card.delete_confirm.confirm',
                                        )}
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogHeader>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>
            </CardFooter>
        </Card>
    );
}

export default DeliveryAddressCard;
