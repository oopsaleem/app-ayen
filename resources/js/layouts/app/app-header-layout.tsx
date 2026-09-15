import { AppContent } from '@/components/app-content';
import AppDirectionProvider from '@/components/app-direction-provider';
import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
import type { AppLayoutProps } from '@/types';

export default function AppHeaderLayout({
    children,
    breadcrumbs,
}: AppLayoutProps) {
    return (
        <AppDirectionProvider>
            <AppShell variant="header">
                <AppHeader breadcrumbs={breadcrumbs} />
                <AppContent variant="header">{children}</AppContent>
            </AppShell>
        </AppDirectionProvider>
    );
}
