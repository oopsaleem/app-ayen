import { useTranslation } from 'react-i18next';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { unitPriceFor } from '@/lib/order-cart';
import type { Dish, Line } from '@/lib/order-cart';

export function DishSelectorCard({
    dish,
    line,
    onChange,
    fieldName,
}: {
    dish: Dish;
    line: Line;
    onChange: (line: Line) => void;
    fieldName?: (
        suffix: 'quantity' | 'serving_size_id' | 'option_ids',
    ) => string;
}) {
    const { t } = useTranslation();

    return (
        <div className="rounded-lg border p-4">
            <div className="flex items-center justify-between gap-4">
                <div>
                    <div className="font-medium">{dish.name_en}</div>
                    {dish.description_en ? (
                        <div className="text-sm text-muted-foreground">
                            {dish.description_en}
                        </div>
                    ) : null}
                    <div className="text-sm">{unitPriceFor(dish, line)}</div>
                </div>

                <div className="w-24">
                    <Label htmlFor={`quantity-${dish.id}`}>
                        {t('orders.quantity')}
                    </Label>
                    <Input
                        id={`quantity-${dish.id}`}
                        name={fieldName?.('quantity')}
                        type="number"
                        min={0}
                        max={99}
                        value={line.quantity}
                        onChange={(e) =>
                            onChange({
                                ...line,
                                quantity: Number(e.target.value),
                            })
                        }
                    />
                </div>
            </div>

            {dish.serving_sizes.length > 0 ? (
                <div className="mt-3">
                    <Label>{t('orders.serving_size')}</Label>
                    <Select
                        name={fieldName?.('serving_size_id')}
                        value={line.serving_size_id?.toString() ?? ''}
                        onValueChange={(value) =>
                            onChange({
                                ...line,
                                serving_size_id: value
                                    ? Number(value)
                                    : undefined,
                            })
                        }
                    >
                        <SelectTrigger className="mt-1 w-full">
                            <SelectValue
                                placeholder={t('orders.serving_size')}
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {dish.serving_sizes.map((size) => (
                                <SelectItem
                                    key={size.id}
                                    value={String(size.id)}
                                >
                                    {size.name_en} ({size.price})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            ) : null}

            {dish.options.length > 0 ? (
                <div className="mt-3">
                    <Label>{t('orders.options')}</Label>
                    <div className="mt-2 grid gap-2">
                        {dish.options.map((option) => (
                            <label
                                key={option.id}
                                className="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    name={fieldName?.('option_ids')}
                                    value={String(option.id)}
                                    checked={line.option_ids.includes(
                                        option.id,
                                    )}
                                    onCheckedChange={(checked) =>
                                        onChange({
                                            ...line,
                                            option_ids:
                                                checked === true
                                                    ? [
                                                          ...line.option_ids,
                                                          option.id,
                                                      ]
                                                    : line.option_ids.filter(
                                                          (id) =>
                                                              id !== option.id,
                                                      ),
                                        })
                                    }
                                />
                                {option.name_en} ({option.price})
                            </label>
                        ))}
                    </div>
                </div>
            ) : null}

            {line.quantity > 0 ? (
                <p className="mt-2 text-xs text-muted-foreground">
                    {t('orders.in_order')} × {line.quantity}
                </p>
            ) : null}
        </div>
    );
}

export default DishSelectorCard;
