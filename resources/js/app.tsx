import { createInertiaApp } from '@inertiajs/react';
import { baseInertiaConfig } from '@/components/inertia-app';
import '@/i18n';
import { initializeTheme } from '@/hooks/use-appearance';
import { initializeLocale } from '@/hooks/use-locale';
import { createLayoutResolver } from '@/layouts/layout-resolver';
import { AppLayout, AuthLayout, SettingsLayout } from '@/layouts/lazy-layouts';

// Keep component and lazy() declarations out of this entry file. Declaring
// them here makes Vite's React Refresh wrapper re-import it as app.tsx?t=…
// after any HMR update, which runs createInertiaApp twice and leaves a stale
// second React root fighting the live one (e.g. reverting locale switches).
createInertiaApp({
    ...baseInertiaConfig,
    layout: createLayoutResolver({
        app: AppLayout,
        auth: AuthLayout,
        settings: SettingsLayout,
    }),
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
initializeLocale();
