import { Form, Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import CompanyController from '@/actions/App/Http/Controllers/Companies/CompanyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/companies';
import { create as createRestaurant } from '@/routes/restaurants';

type Company = {
    id: number;
    display_name: string;
    description: string | null;
};

export default function CompanyEdit({ company }: { company: Company }) {
    const { t } = useTranslation();

    return (
        <>
            <Head
                title={t('companies.edit.page_title', {
                    name: company.display_name,
                })}
            />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title={t('companies.edit.heading')}
                        description={t('companies.edit.description')}
                    />

                    <Button asChild variant="outline">
                        <Link href={createRestaurant({ company: company.id })}>
                            {t('companies.edit.new_restaurant')}
                        </Link>
                    </Button>
                </div>

                <Form
                    {...CompanyController.update.form(company.id)}
                    options={{ preserveScroll: true }}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="display_name">
                                    {t('companies.fields.display_name')}
                                </Label>
                                <Input
                                    id="display_name"
                                    name="display_name"
                                    defaultValue={company.display_name}
                                    required
                                />
                                <InputError message={errors.display_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">
                                    {t('companies.fields.description')}
                                </Label>
                                <textarea
                                    id="description"
                                    name="description"
                                    rows={3}
                                    defaultValue={company.description ?? ''}
                                    className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm outline-none"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('companies.edit.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CompanyEdit.layout = {
    breadcrumbs: [
        { title: 'companies.index.page_title', href: index() },
        { title: 'companies.edit.page_title', href: index() },
    ],
};
