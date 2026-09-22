import { useSyncExternalStore } from 'react';

function subscribe(): () => void {
    return () => {};
}

function getSnapshot(): boolean {
    return true;
}

function getServerSnapshot(): boolean {
    return false;
}

/**
 * True once hydrated on the client. Used to defer client-only libraries
 * (e.g. mapbox-gl) that would otherwise break during SSR.
 */
export function useIsClient(): boolean {
    return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
