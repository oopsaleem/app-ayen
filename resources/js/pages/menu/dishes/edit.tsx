import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import DishController from '@/actions/App/Http/Controllers/Menu/DishController';
import DishOptionController from '@/actions/App/Http/Controllers/Menu/DishOptionController';
import ServingSizeController from '@/actions/App/Http/Controllers/Menu/ServingSizeController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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

type Dish = {
    id: number;
    kitchen_id: number;
    category_id: number;
    name_en: string;
    name_ar: string;
    price: number;
};

type Option = { id: number; name_en: string };

type DishOption = {
    id: number;
    name_en: string;
    name_ar: string;
    price: number;
};
type ServingSize = {
    id: number;
    name_en: string;
    name_ar: string;
    price: number;
    is_default: boolean;
};

export default function DishEdit({
    dish,
    kitchens,
    categories,
    options,
    servingSizes,
}: {
    dish: Dish;
    kitchens: Option[];
    categories: Option[];
    options: DishOption[];
    servingSizes: ServingSize[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head
                title={t('menu.dishes.edit.page_title', { name: dish.name_en })}
            />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('menu.dishes.edit.heading')}
                />

                <Form
                    {...DishController.update.form(dish.id)}
                    options={{ preserveScroll: true }}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name_en">
                                    {t('menu.dishes.fields.name_en')}
                                </Label>
                                <Input
                                    id="name_en"
                                    name="name_en"
                                    defaultValue={dish.name_en}
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
                                    name="name_ar"
                                    dir="rtl"
                                    defaultValue={dish.name_ar}
                                    required
                                />
                                <InputError message={errors.name_ar} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="price">
                                    {t('menu.dishes.fields.price')}
                                </Label>
                                <Input
                                    id="price"
                                    name="price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    defaultValue={dish.price}
                                    required
                                />
                                <InputError message={errors.price} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="kitchen_id">
                                    {t('menu.dishes.fields.kitchen')}
                                </Label>
                                <Select
                                    name="kitchen_id"
                                    defaultValue={String(dish.kitchen_id)}
                                    required
                                >
                                    <SelectTrigger id="kitchen_id">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {kitchens.map((kitchen) => (
                                            <SelectItem
                                                key={kitchen.id}
                                                value={String(kitchen.id)}
                                            >
                                                {kitchen.name_en}
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
                                    name="category_id"
                                    defaultValue={String(dish.category_id)}
                                    required
                                >
                                    <SelectTrigger id="category_id">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((category) => (
                                            <SelectItem
                                                key={category.id}
                                                value={String(category.id)}
                                            >
                                                {category.name_en}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.category_id} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('menu.dishes.edit.submit')}
                            </Button>
                        </>
                    )}
                </Form>

                <div className="space-y-3 border-t pt-6">
                    <Heading
                        variant="small"
                        title={t('menu.dishes.options.heading')}
                    />

                    {options.map((option) => (
                        <div
                            key={option.id}
                            className="flex items-center gap-2 rounded-lg border p-3"
                        >
                            <span className="flex-1">
                                {option.name_en} — {option.price}
                            </span>
                            <Form
                                {...DishOptionController.destroy.form(
                                    option.id,
                                )}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        size="sm"
                                        disabled={processing}
                                    >
                                        {t('menu.dishes.options.remove')}
                                    </Button>
                                )}
                            </Form>
                        </div>
                    ))}

                    <Form
                        {...DishOptionController.store.form(dish.id)}
                        className="flex items-end gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="option_name_en">
                                        {t('menu.dishes.fields.name_en')}
                                    </Label>
                                    <Input
                                        id="option_name_en"
                                        name="name_en"
                                        required
                                    />
                                    <InputError message={errors.name_en} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="option_name_ar">
                                        {t('menu.dishes.fields.name_ar')}
                                    </Label>
                                    <Input
                                        id="option_name_ar"
                                        name="name_ar"
                                        dir="rtl"
                                        required
                                    />
                                    <InputError message={errors.name_ar} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="option_price">
                                        {t('menu.dishes.fields.price')}
                                    </Label>
                                    <Input
                                        id="option_price"
                                        name="price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                    />
                                    <InputError message={errors.price} />
                                </div>
                                <Button type="submit" disabled={processing}>
                                    {t('menu.dishes.options.add')}
                                </Button>
                            </>
                        )}
                    </Form>
                </div>

                <div className="space-y-3 border-t pt-6">
                    <Heading
                        variant="small"
                        title={t('menu.dishes.serving_sizes.heading')}
                    />

                    {servingSizes.map((servingSize) => (
                        <div
                            key={servingSize.id}
                            className="flex items-center gap-2 rounded-lg border p-3"
                        >
                            <span className="flex-1">
                                {servingSize.name_en} — {servingSize.price}
                                {servingSize.is_default
                                    ? ` (${t('menu.dishes.serving_sizes.default')})`
                                    : ''}
                            </span>
                            <Form
                                {...ServingSizeController.destroy.form(
                                    servingSize.id,
                                )}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        size="sm"
                                        disabled={processing}
                                    >
                                        {t('menu.dishes.serving_sizes.remove')}
                                    </Button>
                                )}
                            </Form>
                        </div>
                    ))}

                    <Form
                        {...ServingSizeController.store.form(dish.id)}
                        className="flex items-end gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="serving_size_name_en">
                                        {t('menu.dishes.fields.name_en')}
                                    </Label>
                                    <Input
                                        id="serving_size_name_en"
                                        name="name_en"
                                        required
                                    />
                                    <InputError message={errors.name_en} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="serving_size_name_ar">
                                        {t('menu.dishes.fields.name_ar')}
                                    </Label>
                                    <Input
                                        id="serving_size_name_ar"
                                        name="name_ar"
                                        dir="rtl"
                                        required
                                    />
                                    <InputError message={errors.name_ar} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="serving_size_price">
                                        {t('menu.dishes.fields.price')}
                                    </Label>
                                    <Input
                                        id="serving_size_price"
                                        name="price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                    />
                                    <InputError message={errors.price} />
                                </div>
                                <div className="flex items-center gap-2 pb-2">
                                    <Checkbox
                                        id="serving_size_is_default"
                                        name="is_default"
                                    />
                                    <Label htmlFor="serving_size_is_default">
                                        {t('menu.dishes.serving_sizes.default')}
                                    </Label>
                                </div>
                                <Button type="submit" disabled={processing}>
                                    {t('menu.dishes.serving_sizes.add')}
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}
