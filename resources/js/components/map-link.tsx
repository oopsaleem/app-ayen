import type { ReactNode } from 'react';

export interface MapLinkProps {
    waypoints: { lat: number; lng: number }[];
    children?: ReactNode;
    className?: string;
}

export function MapLink({ waypoints, children, className }: MapLinkProps) {
    if (waypoints.length === 0) {
        return null;
    }

    if (waypoints.length === 1) {
        const { lat, lng } = waypoints[0];
        const googleMapsUrl = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;

        return (
            <a
                href={googleMapsUrl}
                target="_blank"
                rel="noopener noreferrer"
                className={className}
            >
                {children}
            </a>
        );
    }

    const origin = waypoints[0];
    const destination = waypoints[waypoints.length - 1];
    const waypointsParam = waypoints
        .slice(1, -1)
        .map(({ lat, lng }) => `${lat},${lng}`)
        .join('|');

    const googleMapsUrl = `https://www.google.com/maps/dir/?api=1&origin=${origin.lat},${origin.lng}&destination=${destination.lat},${destination.lng}&waypoints=${waypointsParam}`;

    return (
        <a
            href={googleMapsUrl}
            target="_blank"
            rel="noopener noreferrer"
            className={className}
        >
            {children}
        </a>
    );
}

export default MapLink;
