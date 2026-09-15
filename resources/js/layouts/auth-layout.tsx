import { useTranslation } from 'react-i18next';
import AppDirectionProvider from '@/components/app-direction-provider';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    const { t } = useTranslation();

    const translatedTitle = title ? t(title) : undefined;
    const translatedDescription = description ? t(description) : undefined;

    return (
        <AppDirectionProvider>
            <AuthLayoutTemplate
                title={translatedTitle}
                description={translatedDescription}
            >
                {children}
            </AuthLayoutTemplate>
        </AppDirectionProvider>
    );
}
