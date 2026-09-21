import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import CompanyController from '@/actions/App/Http/Controllers/Companies/CompanyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/companies';

export default function CompanyCreate() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('companies.create.page_title')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('companies.create.heading')}
                    description={t('companies.create.description')}
                />

                <Form {...CompanyController.store.form()} className="max-w-xl space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="display_name">
                                    {t('companies.fields.display_name')}
                                </Label>
                                <Input id="display_name" name="display_name" required />
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
                                    className="border-input flex w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-sm outline-none"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                {t('companies.create.submit')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CompanyCreate.layout = {
    breadcrumbs: [
        { title: 'companies.index.page_title', href: index() },
        { title: 'companies.create.page_title', href: index() },
    ],
};
