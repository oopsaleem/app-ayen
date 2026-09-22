import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import CategoryController from '@/actions/App/Http/Controllers/Menu/CategoryController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Restaurant = { id: number; name_en: string };
type ParentOption = { id: number; name_en: string };

export default function CategoryCreate({
    restaurant,
    parents,
}: {
    restaurant: Restaurant;
    parents: ParentOption[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('menu.categories.create.page_title')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('menu.categories.create.heading')}
                />

                <Form
                    {...CategoryController.store.form(restaurant.id)}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name_en">
                                    {t('menu.categories.fields.name_en')}
                                </Label>
                                <Input id="name_en" name="name_en" required />
                                <InputError message={errors.name_en} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name_ar">
                                    {t('menu.categories.fields.name_ar')}
                                </Label>
                                <Input
                                    id="name_ar"
                                    name="name_ar"
                                    dir="rtl"
                                    required
                                />
                                <InputError message={errors.name_ar} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="parent_id">
                                    {t('menu.categories.fields.parent')}
                                </Label>
                                <Select name="parent_id">
                                    <SelectTrigger id="parent_id">
                                        <SelectValue
                                            placeholder={t(
                                                'menu.categories.index.top_level',
                                            )}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {parents.map((parent) => (
                                            <SelectItem
                                                key={parent.id}
                                                value={String(parent.id)}
                                            >
                                                {parent.name_en}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.parent_id} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('menu.categories.create.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
