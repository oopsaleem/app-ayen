import { lazy } from 'react';

// Lazy-loaded so the welcome page (and any page that opts out of a layout)
// doesn't have to download authenticated-app chrome (nav, team switcher,
// settings tabs) it never renders. The SSR entry (ssr.tsx) must import these
// layouts statically instead: renderToString cannot wait for lazy() to
// resolve. Both entries map page names to layouts via layout-resolver.ts.
export const AppLayout = lazy(() => import('@/layouts/app-layout'));
export const AuthLayout = lazy(() => import('@/layouts/auth-layout'));
export const SettingsLayout = lazy(() => import('@/layouts/settings/layout'));
