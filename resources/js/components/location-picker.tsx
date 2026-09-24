import { Loader2, MapPin, MapPinCheckInside } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import Map, { Marker } from 'react-map-gl/mapbox';
import type { ViewState } from 'react-map-gl/mapbox';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAppearance } from '@/hooks/use-appearance';
import { useIsClient } from '@/hooks/use-is-client';
import { getAddressFromCoords, getCoordsFromAddress } from '@/lib/geocoding';

const DEFAULT_CENTER = {
    lat: 15.3547,
    lng: 44.2066,
};

export interface LocationPickerValue {
    address: string;
    lat: number;
    lng: number;
}

interface LocationPickerProps {
    /** Base name used to render `${name}[address]`, `${name}[lat]`, `${name}[lng]` fields. */
    name: string;
    /** Existing value, e.g. when editing a restaurant that already has an address. */
    defaultValue?: LocationPickerValue;
    /** Inertia's flat, dot-keyed error map. */
    errors?: Record<string, string | undefined>;
    label?: string;
}

export default function LocationPicker({
    name,
    defaultValue,
    errors,
    label,
}: LocationPickerProps) {
    const { t } = useTranslation();
    const { appearance } = useAppearance();
    const isClient = useIsClient();
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState('');
    const [address, setAddress] = useState(defaultValue?.address ?? '');
    const [selectedLocation, setSelectedLocation] = useState<{
        lat: number;
        lng: number;
    } | null>(
        defaultValue ? { lat: defaultValue.lat, lng: defaultValue.lng } : null,
    );
    const [viewport, setViewport] = useState<Partial<ViewState>>({
        latitude: defaultValue?.lat ?? DEFAULT_CENTER.lat,
        longitude: defaultValue?.lng ?? DEFAULT_CENTER.lng,
        zoom: defaultValue ? 15 : 13,
    });

    useEffect(() => {
        if (defaultValue || !('geolocation' in navigator)) {
            return;
        }

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                const { latitude, longitude } = position.coords;

                setViewport({ latitude, longitude, zoom: 15 });
                setSelectedLocation({ lat: latitude, lng: longitude });
                setAddress(await getAddressFromCoords(latitude, longitude));
            },
            () => {
                setError(t('location_picker.geolocation_denied'));
            },
        );
    }, [defaultValue, t]);

    const handleMapClick = async (event: mapboxgl.MapMouseEvent) => {
        const { lat, lng } = event.lngLat;

        setError('');
        setSelectedLocation({ lat, lng });
        setIsLoading(true);
        setAddress(await getAddressFromCoords(lat, lng));
        setIsLoading(false);
    };

    const handleAddressSearch = async () => {
        if (!address.trim()) {
            return;
        }

        setIsLoading(true);
        setError('');

        const location = await getCoordsFromAddress(address);

        if (location) {
            setSelectedLocation(location);
            setViewport({
                latitude: location.lat,
                longitude: location.lng,
                zoom: 15,
            });

            if (location.address) {
                setAddress(location.address);
            }
        } else {
            setError(t('location_picker.address_not_found'));
        }

        setIsLoading(false);
    };

    if (!isClient) {
        return null;
    }

    const addressError = errors?.[`${name}.address`];
    const latError = errors?.[`${name}.lat`];
    const lngError = errors?.[`${name}.lng`];

    return (
        <div className="space-y-2">
            <Label htmlFor={`${name}_address`}>
                {label ?? t('location_picker.address_label')}
            </Label>
            <div className="relative">
                <MapPin className="absolute inset-s-3 top-3 size-4 text-muted-foreground" />
                <Input
                    id={`${name}_address`}
                    name={`${name}[address]`}
                    value={address}
                    onChange={(event) => setAddress(event.target.value)}
                    className="ps-9"
                    placeholder={t('location_picker.placeholder')}
                    required
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            handleAddressSearch();
                        }
                    }}
                />
            </div>
            <input
                type="hidden"
                name={`${name}[lat]`}
                value={selectedLocation?.lat ?? ''}
                readOnly
            />
            <input
                type="hidden"
                name={`${name}[lng]`}
                value={selectedLocation?.lng ?? ''}
                readOnly
            />

            {error || addressError ? (
                <p className="text-sm text-destructive">
                    {error || addressError}
                </p>
            ) : null}
            <InputError message={latError} />
            <InputError message={lngError} />

            <div className="relative h-75 overflow-hidden rounded-lg border">
                <Map
                    {...viewport}
                    onMove={(event) => setViewport(event.viewState)}
                    onClick={handleMapClick}
                    mapStyle={
                        appearance === 'dark'
                            ? 'mapbox://styles/mapbox/dark-v11'
                            : 'mapbox://styles/mapbox/streets-v12'
                    }
                    mapboxAccessToken={import.meta.env.VITE_MAPBOX_TOKEN}
                >
                    {selectedLocation && (
                        <Marker
                            latitude={selectedLocation.lat}
                            longitude={selectedLocation.lng}
                            anchor="bottom"
                        >
                            <MapPinCheckInside className="-mt-6 size-5 text-foreground" />
                        </Marker>
                    )}
                </Map>

                {isLoading && (
                    <div className="absolute inset-0 flex items-center justify-center bg-white/75 dark:bg-gray-900/75">
                        <Loader2 className="size-6 animate-spin text-primary" />
                    </div>
                )}
            </div>
        </div>
    );
}
