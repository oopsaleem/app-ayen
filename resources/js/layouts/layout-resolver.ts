import type { ComponentType } from 'react';

type LayoutComponent = ComponentType<any>;

type LayoutComponents = {
    app: LayoutComponent;
    auth: LayoutComponent;
    settings: LayoutComponent;
};

/**
 * Map a page name to the layout(s) that wrap it. Shared by the client entry
 * (lazy layouts for code splitting) and the SSR entry (static layouts,
 * because Inertia's renderToString cannot wait for React.lazy() to resolve)
 * so the two entries stay in sync.
 */
export function createLayoutResolver({
    app,
    auth,
    settings,
}: LayoutComponents) {
    return (
        name: string,
    ): LayoutComponent | [LayoutComponent, LayoutComponent] | null => {
        switch (true) {
            case name === 'welcome':
                return null;

            case name.startsWith('storefront/'):
                return null;

            case name.startsWith('auth/'):
            case name.startsWith('setup/'):
                return auth;

            case name.startsWith('settings/'):
            case name.startsWith('teams/'):
                return [app, settings];

            default:
                return app;
        }
    };
}
