import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/companies';

type Company = {
    id: number;
    display_name: string;
    description: string | null;
    restaurants_count: number;
};

export default function CompaniesIndex({ companies }: { companies: Company[] }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('companies.index.page_title')} />

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title={t('companies.index.heading')}
                        description={t('companies.index.description')}
                    />

                    <Button asChild>
                        <Link href={create()}>
                            <Plus /> {t('companies.index.new_company')}
                        </Link>
                    </Button>
                </div>

                <div className="space-y-3">
                    {companies.map((company) => (
                        <Link
                            key={company.id}
                            href={edit(company.id)}
                            className="flex items-center justify-between gap-4 rounded-lg border p-4 hover:bg-accent"
                        >
                            <div>
                                <div className="font-medium">{company.display_name}</div>
                                {company.description ? (
                                    <div className="text-sm text-muted-foreground">
                                        {company.description}
                                    </div>
                                ) : null}
                            </div>

                            <span className="text-sm text-muted-foreground">
                                {t('companies.index.restaurants_count', {
                                    count: company.restaurants_count,
                                })}
                            </span>
                        </Link>
                    ))}

                    {companies.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            {t('companies.index.no_companies')}
                        </p>
                    ) : null}
                </div>
            </div>
        </>
    );
}

CompaniesIndex.layout = {
    breadcrumbs: [{ title: 'companies.index.page_title', href: index() }],
};
