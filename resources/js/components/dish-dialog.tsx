import { useForm } from '@inertiajs/react';
import { ImagePlus, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import DishController from '@/actions/App/Http/Controllers/Menu/DishController';
import {
    NewOptionRows,
    NewServingSizeRows,
    SavedOptionRows,
    SavedServingSizeRows,
} from '@/components/dish-child-rows';
import type {
    DishOptionRow,
    ServingSizeRow,
} from '@/components/dish-child-rows';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';

/**
 * Room left in the request for the text fields sent alongside the images.
 */
const REQUEST_OVERHEAD_BYTES = 64 * 1024;

const BYTES_PER_MEGABYTE = 1024 * 1024;

export type DishDetail = {
    id: number;
    kitchen_id: number;
    category_id: number;
    name_en: string;
    name_ar: string;
    description_en: string | null;
    description_ar: string | null;
    price: string;
    is_available: boolean;
    images: string[];
    image_urls: string[];
    options: DishOptionRow[];
    serving_sizes: ServingSizeRow[];
};

export type NamedOption = { id: number; name_en: string; name_ar: string };

export type ImageLimits = {
    max_images: number;
    max_image_bytes: number;
    max_request_bytes: number;
};

type ImageSelection =
    | { accepted: File[]; error: null }
    | { accepted: File[]; error: 'file_too_large'; fileName: string }
    | { accepted: File[]; error: 'request_too_large' };

/**
 * Keep the picked files that fit the server's per-image and per-request
 * limits, so an oversized upload never reaches PHP (which rejects it with
 * a 413 before validation can run).
 */
function selectImages(
    picked: File[],
    alreadySelected: File[],
    freeSlots: number,
    limits: ImageLimits,
): ImageSelection {
    const tooLarge = picked.find((file) => file.size > limits.max_image_bytes);

    if (tooLarge) {
        return {
            accepted: [],
            error: 'file_too_large',
            fileName: tooLarge.name,
        };
    }

    const accepted = picked.slice(0, freeSlots);
    const totalBytes = [...alreadySelected, ...accepted].reduce(
        (sum, file) => sum + file.size,
        0,
    );

    if (totalBytes > limits.max_request_bytes - REQUEST_OVERHEAD_BYTES) {
        return { accepted: [], error: 'request_too_large' };
    }

    return { accepted, error: null };
}

function toMegabytes(bytes: number): string {
    return String(Math.round((bytes / BYTES_PER_MEGABYTE) * 10) / 10);
}

type DishFormData = {
    name_en: string;
    name_ar: string;
    description_en: string;
    description_ar: string;
    price: string;
    is_available: boolean;
    kitchen_id: string;
    category_id: string;
    images: File[];
    removed_images: string[];
    options: Omit<DishOptionRow, 'id'>[];
    serving_sizes: Omit<ServingSizeRow, 'id'>[];
};

type DishDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    dish: DishDetail | null;
    restaurantId: number;
    imageLimits: ImageLimits;
    kitchens: NamedOption[];
    categories: NamedOption[];
};

/**
 * Create or edit a dish in a dialog. New dishes send their options and
 * serving sizes with the dish; existing dishes save each row on its own.
 */
export function DishDialog({
    open,
    onOpenChange,
    dish,
    ...rest
}: DishDialogProps) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-6xl">
                <DialogHeader>
                    <DialogTitle>
                        {dish
                            ? t('menu.dishes.dialog.edit_title', {
                                  name: dish.name_en,
                              })
                            : t('menu.dishes.dialog.create_title')}
                    </DialogTitle>
                </DialogHeader>

                {open ? (
                    <DishForm
                        key={dish?.id ?? 'new'}
                        dish={dish}
                        onClose={() => onOpenChange(false)}
                        {...rest}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function DishForm({
    dish,
    restaurantId,
    imageLimits,
    kitchens,
    categories,
    onClose,
}: Omit<DishDialogProps, 'open' | 'onOpenChange'> & { onClose: () => void }) {
    const { t } = useTranslation();
    const { locale } = useLocale();
    const localizedName = (item: NamedOption) =>
        locale === 'ar' ? item.name_ar : item.name_en;

    const form = useForm<DishFormData>({
        name_en: dish?.name_en ?? '',
        name_ar: dish?.name_ar ?? '',
        description_en: dish?.description_en ?? '',
        description_ar: dish?.description_ar ?? '',
        price: dish?.price ?? '',
        is_available: dish?.is_available ?? true,
        kitchen_id: dish ? String(dish.kitchen_id) : '',
        category_id: dish ? String(dish.category_id) : '',
        images: [],
        removed_images: [],
        options: [],
        serving_sizes: [],
    });
    const { data, setData, errors, processing } = form;
    const [selectionError, setSelectionError] = useState<string | null>(null);

    const keptImages = (dish?.images ?? [])
        .map((path, index) => ({ path, url: dish!.image_urls[index] }))
        .filter(({ path }) => !data.removed_images.includes(path));
    const imageCount = keptImages.length + data.images.length;

    const newImageUrls = useMemo(
        () => data.images.map((file) => URL.createObjectURL(file)),
        [data.images],
    );

    useEffect(
        () => () => newImageUrls.forEach((url) => URL.revokeObjectURL(url)),
        [newImageUrls],
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: onClose };

        if (dish) {
            // Files need a multipart POST, so spoof PATCH. Existing dishes
            // save their options and serving sizes row by row instead.
            form.transform((fields) => {
                const payload: Record<string, unknown> = {
                    ...fields,
                    _method: 'patch',
                };
                delete payload.options;
                delete payload.serving_sizes;

                return payload;
            });
            form.post(DishController.update.url(dish.id), options);

            return;
        }

        form.post(DishController.store.url(restaurantId), options);
    };

    const addImages = (picked: File[]) => {
        const selection = selectImages(
            picked,
            data.images,
            imageLimits.max_images - imageCount,
            imageLimits,
        );

        if (selection.error === 'file_too_large') {
            setSelectionError(
                t('menu.dishes.images.file_too_large', {
                    name: selection.fileName,
                    size: toMegabytes(imageLimits.max_image_bytes),
                }),
            );

            return;
        }

        if (selection.error === 'request_too_large') {
            setSelectionError(
                t('menu.dishes.images.request_too_large', {
                    size: toMegabytes(imageLimits.max_request_bytes),
                }),
            );

            return;
        }

        setSelectionError(null);
        setData('images', [...data.images, ...selection.accepted]);
    };

    const imageErrors = Object.entries(errors)
        .filter(([key]) => key === 'images' || key.startsWith('images.'))
        .map(([, message]) => message);

    return (
        <div className="space-y-8">
            <form onSubmit={submit} className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="name_en">
                            {t('menu.dishes.fields.name_en')}
                        </Label>
                        <Input
                            id="name_en"
                            dir="ltr"
                            value={data.name_en}
                            onChange={(e) => setData('name_en', e.target.value)}
                            required
                        />
                        <InputError message={errors.name_en} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="name_ar">
                            {t('menu.dishes.fields.name_ar')}
                        </Label>
                        <Input
                            id="name_ar"
                            dir="rtl"
                            value={data.name_ar}
                            onChange={(e) => setData('name_ar', e.target.value)}
                            required
                        />
                        <InputError message={errors.name_ar} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description_en">
                            {t('menu.dishes.fields.description_en')}
                        </Label>
                        <Textarea
                            id="description_en"
                            dir="ltr"
                            value={data.description_en}
                            onChange={(e) =>
                                setData('description_en', e.target.value)
                            }
                        />
                        <InputError message={errors.description_en} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description_ar">
                            {t('menu.dishes.fields.description_ar')}
                        </Label>
                        <Textarea
                            id="description_ar"
                            dir="rtl"
                            value={data.description_ar}
                            onChange={(e) =>
                                setData('description_ar', e.target.value)
                            }
                        />
                        <InputError message={errors.description_ar} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="price">
                            {t('menu.dishes.fields.price')}
                        </Label>
                        <Input
                            id="price"
                            type="number"
                            step="0.01"
                            min="0"
                            value={data.price}
                            onChange={(e) => setData('price', e.target.value)}
                            required
                        />
                        <InputError message={errors.price} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="is_available">
                            {t('menu.dishes.fields.is_available')}
                        </Label>
                        <div className="flex h-9 items-center">
                            <Switch
                                id="is_available"
                                checked={data.is_available}
                                onCheckedChange={(checked) =>
                                    setData('is_available', checked)
                                }
                            />
                        </div>
                        <InputError message={errors.is_available} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="kitchen_id">
                            {t('menu.dishes.fields.kitchen')}
                        </Label>
                        <Select
                            value={data.kitchen_id}
                            onValueChange={(value) =>
                                setData('kitchen_id', value)
                            }
                        >
                            <SelectTrigger id="kitchen_id">
                                <SelectValue
                                    placeholder={t(
                                        'menu.dishes.fields.kitchen_placeholder',
                                    )}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {kitchens.map((kitchen) => (
                                    <SelectItem
                                        key={kitchen.id}
                                        value={String(kitchen.id)}
                                    >
                                        {localizedName(kitchen)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.kitchen_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="category_id">
                            {t('menu.dishes.fields.category')}
                        </Label>
                        <Select
                            value={data.category_id}
                            onValueChange={(value) =>
                                setData('category_id', value)
                            }
                        >
                            <SelectTrigger id="category_id">
                                <SelectValue
                                    placeholder={t(
                                        'menu.dishes.fields.category_placeholder',
                                    )}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.id}
                                        value={String(category.id)}
                                    >
                                        {localizedName(category)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.category_id} />
                    </div>

                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="images">
                            {t('menu.dishes.fields.images')}
                        </Label>
                        <div className="flex flex-wrap gap-3">
                            {keptImages.map(({ path, url }) => (
                                <ImageThumbnail
                                    key={path}
                                    src={url}
                                    removeLabel={t('menu.dishes.images.remove')}
                                    onRemove={() =>
                                        setData('removed_images', [
                                            ...data.removed_images,
                                            path,
                                        ])
                                    }
                                />
                            ))}
                            {newImageUrls.map((url, index) => (
                                <ImageThumbnail
                                    key={url}
                                    src={url}
                                    removeLabel={t('menu.dishes.images.remove')}
                                    onRemove={() =>
                                        setData(
                                            'images',
                                            data.images.filter(
                                                (_, i) => i !== index,
                                            ),
                                        )
                                    }
                                />
                            ))}
                            {imageCount < imageLimits.max_images ? (
                                <label
                                    htmlFor="images"
                                    className="flex size-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed text-xs text-muted-foreground hover:bg-accent"
                                >
                                    <ImagePlus className="size-5" />
                                    {t('menu.dishes.images.add')}
                                    <input
                                        id="images"
                                        type="file"
                                        accept="image/*"
                                        multiple
                                        className="sr-only"
                                        onChange={(e) => {
                                            addImages(
                                                Array.from(
                                                    e.target.files ?? [],
                                                ),
                                            );
                                            e.target.value = '';
                                        }}
                                    />
                                </label>
                            ) : null}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {t('menu.dishes.images.hint', {
                                max: imageLimits.max_images,
                                size: toMegabytes(imageLimits.max_image_bytes),
                            })}
                        </p>
                        <InputError message={selectionError ?? undefined} />
                        {imageErrors.map((message) => (
                            <InputError key={message} message={message} />
                        ))}
                    </div>
                </div>

                {dish ? null : (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <NewOptionRows
                            rows={data.options}
                            errors={errors}
                            onChange={(rows) => setData('options', rows)}
                        />
                        <NewServingSizeRows
                            rows={data.serving_sizes}
                            errors={errors}
                            onChange={(rows) => setData('serving_sizes', rows)}
                        />
                    </div>
                )}

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('menu.dishes.dialog.cancel')}
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? <Spinner /> : null}
                        {dish
                            ? t('menu.dishes.dialog.save')
                            : t('menu.dishes.dialog.create')}
                    </Button>
                </DialogFooter>
            </form>

            {dish ? (
                <div className="grid gap-6 border-t pt-6 lg:grid-cols-2">
                    <SavedOptionRows dishId={dish.id} rows={dish.options} />
                    <SavedServingSizeRows
                        dishId={dish.id}
                        rows={dish.serving_sizes}
                    />
                </div>
            ) : null}
        </div>
    );
}

function ImageThumbnail({
    src,
    removeLabel,
    onRemove,
}: {
    src: string;
    removeLabel: string;
    onRemove: () => void;
}) {
    return (
        <div className="relative size-24 overflow-hidden rounded-md border">
            <img src={src} alt="" className="size-full object-cover" />
            <Button
                type="button"
                variant="secondary"
                size="icon"
                className="absolute end-1 top-1 size-6"
                aria-label={removeLabel}
                onClick={onRemove}
            >
                <X />
            </Button>
        </div>
    );
}
