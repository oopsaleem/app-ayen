import { createInertiaApp } from '@inertiajs/react';
import InertiaApp from '@/components/inertia-app';
import '@/i18n';
import { initializeTheme } from '@/hooks/use-appearance';
import { initializeLocale } from '@/hooks/use-locale';
import { AppLayout, AuthLayout, SettingsLayout } from '@/layouts/lazy-layouts';

// Keep component and lazy() declarations out of this entry file. Declaring
// them here makes Vite's React Refresh wrapper re-import it as app.tsx?t=…
// after any HMR update, which runs createInertiaApp twice and leaves a stale
// second React root fighting the live one (e.g. reverting locale switches).
const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('storefront/'):
                return null;
            case name.startsWith('auth/'):
            case name.startsWith('setup/'):
                return AuthLayout;
            case name.startsWith('settings/'):
            case name.startsWith('teams/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return <InertiaApp>{app}</InertiaApp>;
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
initializeLocale();
