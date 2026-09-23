import { createInertiaApp } from '@inertiajs/react';
import { baseInertiaConfig } from '@/components/inertia-app';
import '@/i18n';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import { createLayoutResolver } from '@/layouts/layout-resolver';
import SettingsLayout from '@/layouts/settings/layout';

// Inertia SSR renders with the synchronous renderToString, which aborts the
// render when a component suspends, so React.lazy() layouts cannot be used
// here. The client entry (app.tsx) keeps them lazy via layouts/lazy-layouts.ts
// for code splitting; this entry imports the same layouts statically.
//
// The @inertiajs/vite plugin only injects its SSR bootstrap around a
// top-level createInertiaApp() statement, so keep this call as a statement
// (like app.tsx) instead of exporting it directly.
createInertiaApp({
    ...baseInertiaConfig,
    layout: createLayoutResolver({
        app: AppLayout,
        auth: AuthLayout,
        settings: SettingsLayout,
    }),
});
