import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';

type OrderStatus =
    | 'pending'
    | 'confirmed'
    | 'preparing'
    | 'awaiting_delivery'
    | 'awaiting_pickup'
    | 'out_for_delivery'
    | 'closed'
    | 'cancelled';

type HistoryRow = {
    status: OrderStatus;
    previous_status: OrderStatus | null;
    duration_in_previous_status: number | null;
    created_at: string | null;
};

type OrderDetail = {
    id: number;
    status: OrderStatus;
    delivery_mode: 'delivery' | 'pickup';
    total: string;
    created_at?: string;
    restaurant: { name_en: string };
    can_cancel: boolean;
    dishes: {
        id: number;
        name_en: string;
        quantity: number;
        total_price: string;
    }[];
    status_history: HistoryRow[];
};

export default function OrderShow({ order }: { order: OrderDetail }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('orders.show.page_title')} />

            <div className="mx-auto max-w-3xl space-y-6">
                <Heading variant="small" title={t('orders.show.heading')} />

                <div className="rounded-lg border p-4">
                    <div className="flex items-center justify-between gap-4">
                        <div className="space-y-1">
                            <div className="font-medium">
                                #{order.id} · {order.restaurant.name_en}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {order.created_at
                                    ? new Date(order.created_at).toLocaleString(
                                          undefined,
                                          {
                                              dateStyle: 'medium',
                                              timeStyle: 'short',
                                          },
                                      )
                                    : null}
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Badge
                                variant={
                                    order.status === 'cancelled'
                                        ? 'destructive'
                                        : order.status === 'closed'
                                          ? 'default'
                                          : 'secondary'
                                }
                            >
                                {t('orders.index.status.' + order.status)}
                            </Badge>
                            <span className="text-sm text-muted-foreground">
                                {t(
                                    'orders.delivery_mode.' +
                                        order.delivery_mode,
                                )}
                            </span>
                            <span className="font-medium">{order.total}</span>
                        </div>
                    </div>
                </div>

                <div className="rounded-lg border p-4">
                    <Heading variant="small" title={t('orders.show.dishes')} />
                    <ul className="mt-2 space-y-1">
                        {order.dishes.map((dish) => (
                            <li
                                key={dish.id}
                                className="flex justify-between text-sm"
                            >
                                <span>
                                    {dish.name_en} × {dish.quantity}
                                </span>
                                <span>{dish.total_price}</span>
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="rounded-lg border p-4">
                    <Heading
                        variant="small"
                        title={t('orders.show.timeline')}
                    />
                    <ol className="mt-3 space-y-1">
                        {order.status_history.map(
                            (row: HistoryRow, index: number) => (
                                <li
                                    key={index}
                                    className="flex items-center justify-between gap-4 text-sm"
                                >
                                    <span className="flex items-center gap-2">
                                        <Badge variant="outline">
                                            {t(
                                                'orders.index.status.' +
                                                    row.status,
                                            )}
                                        </Badge>
                                    </span>
                                    <span className="text-muted-foreground">
                                        {row.previous_status !== null &&
                                        row.duration_in_previous_status !== null
                                            ? t('orders.show.duration_label', {
                                                  seconds:
                                                      row.duration_in_previous_status,
                                                  previous: t(
                                                      'orders.index.status.' +
                                                          row.previous_status,
                                                  ),
                                              })
                                            : null}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {row.created_at
                                            ? new Date(
                                                  row.created_at,
                                              ).toLocaleString(undefined, {
                                                  dateStyle: 'medium',
                                                  timeStyle: 'short',
                                              })
                                            : null}
                                    </span>
                                </li>
                            ),
                        )}
                    </ol>
                </div>
            </div>
        </>
    );
}
