import { router, useForm } from '@inertiajs/react';
import { Check, Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import DishOptionController from '@/actions/App/Http/Controllers/Menu/DishOptionController';
import ServingSizeController from '@/actions/App/Http/Controllers/Menu/ServingSizeController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export type DishOptionRow = {
    id: number;
    name_en: string;
    name_ar: string;
    price: string;
};

export type ServingSizeRow = {
    id: number;
    name_en: string;
    name_ar: string;
    price: string;
    is_default: boolean;
    servings_count: number;
};

type NewOption = Omit<DishOptionRow, 'id'>;
type NewServingSize = Omit<ServingSizeRow, 'id'>;

const EMPTY_OPTION: NewOption = { name_en: '', name_ar: '', price: '' };
const EMPTY_SERVING_SIZE: NewServingSize = {
    name_en: '',
    name_ar: '',
    price: '',
    is_default: false,
    servings_count: 1,
};

const preserveScroll = { preserveScroll: true };

/**
 * Option rows for a dish that has not been created yet; they are sent
 * along with the dish itself.
 */
export function NewOptionRows({
    rows,
    errors,
    onChange,
}: {
    rows: NewOption[];
    errors: Record<string, string | undefined>;
    onChange: (rows: NewOption[]) => void;
}) {
    const { t } = useTranslation();

    const update = (index: number, changes: Partial<NewOption>) =>
        onChange(
            rows.map((row, i) => (i === index ? { ...row, ...changes } : row)),
        );

    return (
        <RowsTable
            caption={t('menu.dishes.options.heading')}
            headings={optionHeadings(t)}
            addLabel={t('menu.dishes.options.add')}
            onAdd={() => onChange([...rows, EMPTY_OPTION])}
        >
            {rows.map((row, index) => (
                <TableRow key={index}>
                    <OptionCells
                        values={row}
                        errorFor={(field) =>
                            errors[`options.${index}.${field}`]
                        }
                        onChange={(changes) => update(index, changes)}
                    />
                    <TableCell>
                        <RemoveButton
                            label={t('menu.dishes.options.remove')}
                            onClick={() =>
                                onChange(rows.filter((_, i) => i !== index))
                            }
                        />
                    </TableCell>
                </TableRow>
            ))}
        </RowsTable>
    );
}

/**
 * Serving size rows for a dish that has not been created yet. Only one
 * row can be the default.
 */
export function NewServingSizeRows({
    rows,
    errors,
    onChange,
}: {
    rows: NewServingSize[];
    errors: Record<string, string | undefined>;
    onChange: (rows: NewServingSize[]) => void;
}) {
    const { t } = useTranslation();

    const update = (index: number, changes: Partial<NewServingSize>) =>
        onChange(
            rows.map((row, i) => {
                if (i === index) {
                    return { ...row, ...changes };
                }

                return changes.is_default ? { ...row, is_default: false } : row;
            }),
        );

    return (
        <RowsTable
            caption={t('menu.dishes.serving_sizes.heading')}
            headings={servingSizeHeadings(t)}
            addLabel={t('menu.dishes.serving_sizes.add')}
            onAdd={() => onChange([...rows, EMPTY_SERVING_SIZE])}
        >
            {rows.map((row, index) => (
                <TableRow key={index}>
                    <ServingSizeCells
                        values={row}
                        errorFor={(field) =>
                            errors[`serving_sizes.${index}.${field}`]
                        }
                        onChange={(changes) => update(index, changes)}
                    />
                    <TableCell>
                        <RemoveButton
                            label={t('menu.dishes.serving_sizes.remove')}
                            onClick={() =>
                                onChange(rows.filter((_, i) => i !== index))
                            }
                        />
                    </TableCell>
                </TableRow>
            ))}
        </RowsTable>
    );
}

/**
 * Option rows of an existing dish; each row is saved or removed on its own.
 */
export function SavedOptionRows({
    dishId,
    rows,
}: {
    dishId: number;
    rows: DishOptionRow[];
}) {
    const { t } = useTranslation();
    const newRow = useForm<NewOption>(EMPTY_OPTION);

    return (
        <RowsTable
            caption={t('menu.dishes.options.heading')}
            headings={optionHeadings(t)}
            addLabel={t('menu.dishes.options.add')}
            addDisabled={newRow.processing}
            onAdd={() =>
                newRow.post(DishOptionController.store.url(dishId), {
                    ...preserveScroll,
                    onSuccess: () => newRow.reset(),
                })
            }
        >
            {rows.map((row) => (
                <SavedOptionRow key={JSON.stringify(row)} row={row} />
            ))}
            <TableRow>
                <OptionCells
                    values={newRow.data}
                    errorFor={(field) => newRow.errors[field]}
                    onChange={(changes) =>
                        newRow.setData({ ...newRow.data, ...changes })
                    }
                />
                <TableCell />
            </TableRow>
        </RowsTable>
    );
}

function SavedOptionRow({ row }: { row: DishOptionRow }) {
    const { t } = useTranslation();
    const form = useForm<NewOption>({
        name_en: row.name_en,
        name_ar: row.name_ar,
        price: row.price,
    });

    return (
        <TableRow>
            <OptionCells
                values={form.data}
                errorFor={(field) => form.errors[field]}
                onChange={(changes) =>
                    form.setData({ ...form.data, ...changes })
                }
            />
            <TableCell>
                <SavedRowActions
                    isDirty={form.isDirty}
                    processing={form.processing}
                    saveLabel={t('menu.dishes.options.save')}
                    removeLabel={t('menu.dishes.options.remove')}
                    onSave={() =>
                        form.patch(
                            DishOptionController.update.url(row.id),
                            preserveScroll,
                        )
                    }
                    onRemove={() =>
                        router.delete(
                            DishOptionController.destroy.url(row.id),
                            preserveScroll,
                        )
                    }
                />
            </TableCell>
        </TableRow>
    );
}

/**
 * Serving size rows of an existing dish; each row is saved or removed on
 * its own. The server clears the other defaults when one is saved as default.
 */
export function SavedServingSizeRows({
    dishId,
    rows,
}: {
    dishId: number;
    rows: ServingSizeRow[];
}) {
    const { t } = useTranslation();
    const newRow = useForm<NewServingSize>(EMPTY_SERVING_SIZE);

    return (
        <RowsTable
            caption={t('menu.dishes.serving_sizes.heading')}
            headings={servingSizeHeadings(t)}
            addLabel={t('menu.dishes.serving_sizes.add')}
            addDisabled={newRow.processing}
            onAdd={() =>
                newRow.post(ServingSizeController.store.url(dishId), {
                    ...preserveScroll,
                    onSuccess: () => newRow.reset(),
                })
            }
        >
            {rows.map((row) => (
                <SavedServingSizeRow key={JSON.stringify(row)} row={row} />
            ))}
            <TableRow>
                <ServingSizeCells
                    values={newRow.data}
                    errorFor={(field) => newRow.errors[field]}
                    onChange={(changes) =>
                        newRow.setData({ ...newRow.data, ...changes })
                    }
                />
                <TableCell />
            </TableRow>
        </RowsTable>
    );
}

function SavedServingSizeRow({ row }: { row: ServingSizeRow }) {
    const { t } = useTranslation();
    const form = useForm<NewServingSize>({
        name_en: row.name_en,
        name_ar: row.name_ar,
        price: row.price,
        is_default: row.is_default,
        servings_count: row.servings_count,
    });

    return (
        <TableRow>
            <ServingSizeCells
                values={form.data}
                errorFor={(field) => form.errors[field]}
                onChange={(changes) =>
                    form.setData({ ...form.data, ...changes })
                }
            />
            <TableCell>
                <SavedRowActions
                    isDirty={form.isDirty}
                    processing={form.processing}
                    saveLabel={t('menu.dishes.serving_sizes.save')}
                    removeLabel={t('menu.dishes.serving_sizes.remove')}
                    onSave={() =>
                        form.patch(
                            ServingSizeController.update.url(row.id),
                            preserveScroll,
                        )
                    }
                    onRemove={() =>
                        router.delete(
                            ServingSizeController.destroy.url(row.id),
                            preserveScroll,
                        )
                    }
                />
            </TableCell>
        </TableRow>
    );
}

function RowsTable({
    caption,
    headings,
    addLabel,
    addDisabled = false,
    onAdd,
    children,
}: {
    caption: string;
    headings: string[];
    addLabel: string;
    addDisabled?: boolean;
    onAdd: () => void;
    children: ReactNode;
}) {
    return (
        <div className="space-y-2">
            <Table>
                <TableCaption className="mt-0 mb-2 caption-top text-start font-medium text-foreground">
                    {caption}
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        {headings.map((heading) => (
                            <TableHead key={heading} className="text-start">
                                {heading}
                            </TableHead>
                        ))}
                        <TableHead />
                    </TableRow>
                </TableHeader>
                <TableBody>{children}</TableBody>
            </Table>
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={addDisabled}
                onClick={onAdd}
            >
                <Plus /> {addLabel}
            </Button>
        </div>
    );
}

function OptionCells({
    values,
    errorFor,
    onChange,
}: {
    values: NewOption;
    errorFor: (field: keyof NewOption) => string | undefined;
    onChange: (changes: Partial<NewOption>) => void;
}) {
    return (
        <>
            <NameCells
                values={values}
                errorFor={errorFor}
                onChange={onChange}
            />
            <TableCell className="align-top">
                <PriceInput
                    value={values.price}
                    onChange={(price) => onChange({ price })}
                />
                <InputError message={errorFor('price')} />
            </TableCell>
        </>
    );
}

function ServingSizeCells({
    values,
    errorFor,
    onChange,
}: {
    values: NewServingSize;
    errorFor: (field: keyof NewServingSize) => string | undefined;
    onChange: (changes: Partial<NewServingSize>) => void;
}) {
    return (
        <>
            <OptionCells
                values={values}
                errorFor={errorFor}
                onChange={onChange}
            />
            <TableCell className="align-top">
                <Input
                    type="number"
                    min="1"
                    className="w-16"
                    value={values.servings_count}
                    onChange={(e) =>
                        onChange({ servings_count: Number(e.target.value) })
                    }
                />
                <InputError message={errorFor('servings_count')} />
            </TableCell>
            <TableCell className="align-top">
                <div className="flex h-9 items-center">
                    <Switch
                        checked={values.is_default}
                        onCheckedChange={(is_default) =>
                            onChange({ is_default })
                        }
                    />
                </div>
            </TableCell>
        </>
    );
}

function NameCells({
    values,
    errorFor,
    onChange,
}: {
    values: { name_en: string; name_ar: string };
    errorFor: (field: 'name_en' | 'name_ar') => string | undefined;
    onChange: (changes: { name_en?: string; name_ar?: string }) => void;
}) {
    return (
        <>
            <TableCell className="align-top">
                <Input
                    dir="ltr"
                    className="min-w-28"
                    value={values.name_en}
                    onChange={(e) => onChange({ name_en: e.target.value })}
                />
                <InputError message={errorFor('name_en')} />
            </TableCell>
            <TableCell className="align-top">
                <Input
                    dir="rtl"
                    className="min-w-28"
                    value={values.name_ar}
                    onChange={(e) => onChange({ name_ar: e.target.value })}
                />
                <InputError message={errorFor('name_ar')} />
            </TableCell>
        </>
    );
}

function PriceInput({
    value,
    onChange,
}: {
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <Input
            type="number"
            step="0.01"
            min="0"
            className="w-24"
            value={value}
            onChange={(e) => onChange(e.target.value)}
        />
    );
}

function SavedRowActions({
    isDirty,
    processing,
    saveLabel,
    removeLabel,
    onSave,
    onRemove,
}: {
    isDirty: boolean;
    processing: boolean;
    saveLabel: string;
    removeLabel: string;
    onSave: () => void;
    onRemove: () => void;
}) {
    return (
        <div className="flex gap-1">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={saveLabel}
                disabled={!isDirty || processing}
                onClick={onSave}
            >
                <Check />
            </Button>
            <RemoveButton label={removeLabel} onClick={onRemove} />
        </div>
    );
}

function RemoveButton({
    label,
    onClick,
}: {
    label: string;
    onClick: () => void;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            aria-label={label}
            onClick={onClick}
        >
            <Trash2 className="text-destructive" />
        </Button>
    );
}

function optionHeadings(t: (key: string) => string): string[] {
    return [
        t('menu.dishes.fields.name_en'),
        t('menu.dishes.fields.name_ar'),
        t('menu.dishes.fields.price'),
    ];
}

function servingSizeHeadings(t: (key: string) => string): string[] {
    return [
        ...optionHeadings(t),
        t('menu.dishes.fields.servings_count'),
        t('menu.dishes.serving_sizes.default'),
    ];
}
