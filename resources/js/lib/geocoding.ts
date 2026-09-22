export interface GeocodedLocation {
    lat: number;
    lng: number;
    address?: string;
}

export async function getAddressFromCoords(
    lat: number,
    lng: number,
): Promise<string> {
    try {
        const response = await fetch(
            `https://api.mapbox.com/geocoding/v5/mapbox.places/${lng},${lat}.json?access_token=${import.meta.env.VITE_MAPBOX_TOKEN}`,
        );
        const data = await response.json();

        return data.features[0].place_name;
    } catch (error) {
        console.error('Error getting address:', error);

        return '';
    }
}

export async function getCoordsFromAddress(
    address: string,
): Promise<GeocodedLocation | null> {
    try {
        const response = await fetch(
            `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(address)}.json?access_token=${import.meta.env.VITE_MAPBOX_TOKEN}`,
        );
        const data = await response.json();
        const [lng, lat] = data.features[0].center;

        return {
            lat,
            lng,
            address: data.features[0].place_name,
        };
    } catch (error) {
        console.error('Error getting coordinates:', error);

        return null;
    }
}
