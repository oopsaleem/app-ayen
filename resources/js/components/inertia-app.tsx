import type { ReactElement } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useFlashToast } from '@/hooks/use-flash-toast';

export function InertiaApp({ children }: { children: React.ReactNode }) {
    useFlashToast();

    return (
        <TooltipProvider delayDuration={0}>
            {children}
            <Toaster />
        </TooltipProvider>
    );
}

export default InertiaApp;

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Shared by app.tsx and ssr.tsx: the only difference between the two entries
// is the layout resolver (lazy vs. static) and app.tsx's progress-bar config.
export const baseInertiaConfig = {
    title: (title: string) => (title ? `${title} - ${appName}` : appName),
    strictMode: true,
    withApp: (app: ReactElement) => <InertiaApp>{app}</InertiaApp>,
};
