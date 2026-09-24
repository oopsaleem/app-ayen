import { Minus, Plus, ShoppingCart, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useLocale } from '@/hooks/use-locale';
import type { OrderCart } from '@/hooks/use-order-cart';
import { lineTotal, unitPriceFor } from '@/lib/order-cart';

export function CartDrawer({
    cart,
    footer,
}: {
    cart: OrderCart;
    footer: ReactNode;
}) {
    const { t } = useTranslation();
    const { locale } = useLocale();
    const [open, setOpen] = useState(false);
    const itemCount = cart.items.reduce((sum, item) => sum + item.quantity, 0);

    return (
        <>
            <Button
                type="button"
                size="icon"
                className="fixed bottom-4 inset-e-4 z-40 size-12 rounded-full shadow-lg"
                aria-label={t('menu.browse.cart.open')}
                onClick={() => setOpen(true)}
            >
                <ShoppingCart />
                {itemCount > 0 ? (
                    <Badge
                        variant="destructive"
                        className="absolute -top-2 -inset-s-2"
                    >
                        {itemCount}
                    </Badge>
                ) : null}
            </Button>

            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="flex w-full flex-col sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>{t('menu.browse.cart.title')}</SheetTitle>
                    </SheetHeader>

                    {cart.items.length === 0 ? (
                        <div className="flex flex-1 items-center justify-center px-4 text-sm text-muted-foreground">
                            {t('menu.browse.cart.empty')}
                        </div>
                    ) : (
                        <>
                            <div className="flex-1 space-y-4 overflow-y-auto px-4">
                                {cart.items.map((item) => (
                                    <div
                                        key={item.key}
                                        className="space-y-2 border-b pb-4"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="space-y-1">
                                                <p className="font-medium">
                                                    {locale === 'ar'
                                                        ? item.dish.name_ar
                                                        : item.dish.name_en}
                                                </p>
                                                {item.serving_size_id ? (
                                                    <p className="text-sm text-muted-foreground">
                                                        {locale === 'ar'
                                                            ? item.dish.serving_sizes.find(
                                                                  (size) =>
                                                                      size.id ===
                                                                      item.serving_size_id,
                                                              )?.name_ar
                                                            : item.dish.serving_sizes.find(
                                                                  (size) =>
                                                                      size.id ===
                                                                      item.serving_size_id,
                                                              )?.name_en}
                                                    </p>
                                                ) : null}
                                                {item.option_ids.length > 0 ? (
                                                    <ul className="text-sm text-muted-foreground">
                                                        {item.option_ids.map(
                                                            (optionId) => {
                                                                const option =
                                                                    item.dish.options.find(
                                                                        (o) =>
                                                                            o.id ===
                                                                            optionId,
                                                                    );

                                                                return option ? (
                                                                    <li
                                                                        key={
                                                                            optionId
                                                                        }
                                                                    >
                                                                        +{' '}
                                                                        {locale ===
                                                                        'ar'
                                                                            ? option.name_ar
                                                                            : option.name_en}
                                                                    </li>
                                                                ) : null;
                                                            },
                                                        )}
                                                    </ul>
                                                ) : null}
                                                <p className="text-sm">
                                                    {unitPriceFor(
                                                        item.dish,
                                                        item,
                                                    )}{' '}
                                                    ×{' '}
                                                    {item.quantity}
                                                </p>
                                            </div>

                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t(
                                                    'menu.browse.cart.remove',
                                                )}
                                                onClick={() =>
                                                    cart.remove(item.key)
                                                }
                                            >
                                                <Trash2 className="text-destructive" />
                                            </Button>
                                        </div>

                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    aria-label={t(
                                                        'menu.browse.cart.decrease',
                                                    )}
                                                    onClick={() =>
                                                        cart.updateQuantity(
                                                            item.key,
                                                            item.quantity - 1,
                                                        )
                                                    }
                                                >
                                                    <Minus />
                                                </Button>
                                                <span className="w-6 text-center">
                                                    {item.quantity}
                                                </span>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    aria-label={t(
                                                        'menu.browse.cart.increase',
                                                    )}
                                                    onClick={() =>
                                                        cart.updateQuantity(
                                                            item.key,
                                                            item.quantity + 1,
                                                        )
                                                    }
                                                >
                                                    <Plus />
                                                </Button>
                                            </div>
                                            <span className="font-medium">
                                                {lineTotal(item)}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="space-y-4 border-t p-4">
                                <div className="flex items-center justify-between font-medium">
                                    <span>{t('menu.browse.cart.total')}</span>
                                    <span>{cart.total}</span>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {t('menu.browse.cart.total_note')}
                                </p>
                                {footer}
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}

export default CartDrawer;
