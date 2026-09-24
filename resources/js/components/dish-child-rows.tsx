import { Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
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

/**
 * A row in the dish form: saved rows keep their id so the server updates
 * them in place, new rows have none.
 */
export type OptionInput = NewOption & { id?: number };
export type ServingSizeInput = NewServingSize & { id?: number };

const EMPTY_OPTION: OptionInput = { name_en: '', name_ar: '', price: '' };
const EMPTY_SERVING_SIZE: ServingSizeInput = {
    name_en: '',
    name_ar: '',
    price: '',
    is_default: false,
    servings_count: 1,
};

/**
 * Editable option rows of the dish form; they are saved with the dish.
 */
export function OptionRows({
    rows,
    errors,
    onChange,
}: {
    rows: OptionInput[];
    errors: Record<string, string | undefined>;
    onChange: (rows: OptionInput[]) => void;
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
                <TableRow key={row.id ?? `new-${index}`}>
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
 * Editable serving size rows of the dish form; they are saved with the
 * dish. Only one row can be the default.
 */
export function ServingSizeRows({
    rows,
    errors,
    onChange,
}: {
    rows: ServingSizeInput[];
    errors: Record<string, string | undefined>;
    onChange: (rows: ServingSizeInput[]) => void;
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
                <TableRow key={row.id ?? `new-${index}`}>
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

function RowsTable({
    caption,
    headings,
    addLabel,
    onAdd,
    children,
}: {
    caption: string;
    headings: string[];
    addLabel: string;
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
            <Button type="button" variant="outline" size="sm" onClick={onAdd}>
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
