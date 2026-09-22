import type { FeatureCollection } from 'geojson';
import {
    AlertCircle,
    ChefHat,
    Loader2,
    MapPin,
    MapPinCheckInside,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import Map, { Layer, Marker, Source } from 'react-map-gl/mapbox';
import type { ViewState } from 'react-map-gl/mapbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAppearance } from '@/hooks/use-appearance';
import { getAddressFromCoords, getCoordsFromAddress } from '@/lib/geocoding';
import type { GeocodedLocation as Location } from '@/lib/geocoding';

const CENTRAL_LOCATION = {
    lat: 15.3547,
    lng: 44.2066,
};

const MAX_DELIVERY_RADIUS_KM = 5;

export interface AddressFormData {
    id?: number;
    caption?: string;
    address: string;
    lat: number;
    lng: number;
    is_default: boolean;
}

interface SharedAddressFormProps {
    initialData?: AddressFormData;
    onSubmit: (data: AddressFormData) => Promise<void> | void;
    children?: React.ReactNode;
}

export function SharedAddressForm({
    initialData,
    onSubmit,
    children,
}: SharedAddressFormProps) {
    const { t } = useTranslation();
    const { appearance } = useAppearance();
    const [mounted, setMounted] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState('');
    const [isOutOfRange, setIsOutOfRange] = useState(false);
    const [selectedLocation, setSelectedLocation] = useState<Location | null>(
        initialData ? { lat: initialData.lat, lng: initialData.lng } : null,
    );
    const [viewport, setViewport] = useState<Partial<ViewState>>({
        latitude: initialData?.lat || CENTRAL_LOCATION.lat,
        longitude: initialData?.lng || CENTRAL_LOCATION.lng,
        zoom: 13,
    });

    const {
        register,
        handleSubmit,
        setValue,
        watch,
        reset,
        formState: { errors },
    } = useForm<AddressFormData>({
        defaultValues: {
            caption: initialData?.caption || '',
            address: initialData?.address || '',
            lat: initialData?.lat || 0,
            lng: initialData?.lng || 0,
            is_default: initialData?.is_default || false,
        },
    });

    const currentAddress = watch('address');

    useEffect(() => {
        setMounted(true);
    }, []);

    useEffect(() => {
        if (initialData) {
            reset(initialData);
            setViewport({
                latitude: initialData.lat,
                longitude: initialData.lng,
                zoom: 15,
            });
            setSelectedLocation({
                lat: initialData.lat,
                lng: initialData.lng,
            });
        }
    }, [initialData, reset]);

    useEffect(() => {
        if (!initialData && 'geolocation' in navigator) {
            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    const { latitude, longitude } = position.coords;
                    setViewport({
                        latitude,
                        longitude,
                        zoom: 15,
                    });
                    setSelectedLocation({ lat: latitude, lng: longitude });

                    const address = await getAddressFromCoords(
                        latitude,
                        longitude,
                    );
                    const newLocation = { lat: latitude, lng: longitude };

                    if (!isWithinDeliveryRadius(newLocation)) {
                        setIsOutOfRange(true);
                        setError(
                            t('addresses.form.errors.out_of_range', {
                                distance: MAX_DELIVERY_RADIUS_KM,
                            }),
                        );

                        return;
                    }

                    setValue('address', address);
                    setValue('lat', latitude);
                    setValue('lng', longitude);
                },
                () => {
                    setError(t('addresses.form.errors.geolocation_denied'));
                },
            );
        }
    }, [initialData, setValue, t]);

    const handleMapClick = async (event: mapboxgl.MapMouseEvent) => {
        const { lat, lng } = event.lngLat;
        const newLocation = { lat, lng };

        if (!isWithinDeliveryRadius(newLocation)) {
            setIsOutOfRange(true);
            setError(
                t('addresses.form.errors.out_of_range', {
                    distance: MAX_DELIVERY_RADIUS_KM,
                }),
            );

            return;
        }

        setIsOutOfRange(false);
        setError('');
        setSelectedLocation(newLocation);
        setIsLoading(true);

        const address = await getAddressFromCoords(lat, lng);
        setValue('address', address);
        setValue('lat', lat);
        setValue('lng', lng);
        setIsLoading(false);
    };

    const handleAddressSearch = async () => {
        if (!currentAddress?.trim()) {
            return;
        }

        setIsLoading(true);
        setError('');
        setIsOutOfRange(false);

        try {
            const location = await getCoordsFromAddress(currentAddress);

            if (location) {
                if (!isWithinDeliveryRadius(location)) {
                    setIsOutOfRange(true);
                    setError(
                        t('addresses.form.errors.out_of_range', {
                            distance: MAX_DELIVERY_RADIUS_KM,
                        }),
                    );
                    setIsLoading(false);

                    return;
                }

                setSelectedLocation(location);
                setViewport({
                    latitude: location.lat,
                    longitude: location.lng,
                    zoom: 15,
                });

                if (location.address) {
                    setValue('address', location.address);
                    setValue('lat', location.lat);
                    setValue('lng', location.lng);
                }
            }
        } catch {
            setError(t('addresses.form.errors.address_not_found'));
        } finally {
            setIsLoading(false);
        }
    };

    const onSubmitForm = async (data: AddressFormData) => {
        if (isOutOfRange) {
            return;
        }

        try {
            setIsLoading(true);
            await onSubmit({
                ...data,
                id: initialData?.id,
            });
        } finally {
            setIsLoading(false);
        }
    };

    if (!mounted) {
        return null;
    }

    return (
        <form onSubmit={handleSubmit(onSubmitForm)} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="caption">{t('addresses.fields.caption')}</Label>
                <Input
                    id="caption"
                    {...register('caption')}
                    placeholder={t('addresses.form.placeholders.caption')}
                />
            </div>

            <Alert>
                <AlertCircle className="size-4" />
                <AlertDescription>
                    {t('addresses.form.delivery_radius', {
                        distance: MAX_DELIVERY_RADIUS_KM,
                    })}
                </AlertDescription>
            </Alert>

            <div className="space-y-2">
                <Label htmlFor="address">
                    {t('addresses.fields.address')}{' '}
                    <span className="text-destructive">*</span>
                </Label>
                <div className="relative">
                    <MapPin className="absolute start-3 top-3 size-4 text-muted-foreground" />
                    <Input
                        id="address"
                        {...register('address', {
                            required: t(
                                'addresses.form.errors.address_required',
                            ),
                        })}
                        className={`ps-9 ${isOutOfRange ? 'border-red-500' : ''}`}
                        placeholder={t('addresses.form.placeholders.address')}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                handleAddressSearch();
                            }
                        }}
                    />
                </div>
                {(error || errors.address) && (
                    <p className="text-sm text-destructive">
                        {error || errors.address?.message}
                    </p>
                )}
            </div>

            <div className="relative h-[300px] overflow-hidden rounded-lg border">
                <Map
                    {...viewport}
                    onMove={(evt) => setViewport(evt.viewState)}
                    onClick={handleMapClick}
                    mapStyle={
                        appearance === 'dark'
                            ? 'mapbox://styles/mapbox/dark-v11'
                            : 'mapbox://styles/mapbox/streets-v12'
                    }
                    mapboxAccessToken={import.meta.env.VITE_MAPBOX_TOKEN}
                >
                    <Source
                        id="delivery-radius"
                        type="geojson"
                        data={createDeliveryRadiusGeoJSON()}
                    >
                        <Layer
                            id="delivery-radius-fill"
                            type="fill"
                            paint={{
                                'fill-color':
                                    appearance === 'dark'
                                        ? '#ffffff'
                                        : '#000000',
                                'fill-opacity': 0.1,
                            }}
                        />
                        <Layer
                            id="delivery-radius-line"
                            type="line"
                            paint={{
                                'line-color':
                                    appearance === 'dark'
                                        ? '#ffffff'
                                        : '#000000',
                                'line-width': 1,
                                'line-dasharray': [2, 2],
                            }}
                        />
                    </Source>

                    <Marker
                        latitude={CENTRAL_LOCATION.lat}
                        longitude={CENTRAL_LOCATION.lng}
                        anchor="center"
                    >
                        <ChefHat className="size-6 rounded-full border-2 border-primary" />
                    </Marker>

                    {selectedLocation && (
                        <Marker
                            latitude={selectedLocation.lat}
                            longitude={selectedLocation.lng}
                            anchor="bottom"
                        >
                            <MapPinCheckInside
                                className={`-mt-6 size-5 ${isOutOfRange ? 'text-red-500' : 'text-foreground'}`}
                            />
                        </Marker>
                    )}
                </Map>

                {isLoading && (
                    <div className="absolute inset-0 flex items-center justify-center bg-white/75 dark:bg-gray-900/75">
                        <Loader2 className="size-6 animate-spin text-primary" />
                    </div>
                )}
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="is_default"
                    checked={watch('is_default')}
                    onCheckedChange={(checked: boolean) => {
                        setValue('is_default', checked);
                    }}
                />
                <Label htmlFor="is_default">
                    {t('addresses.fields.is_default')}
                </Label>
            </div>

            {children}
        </form>
    );
}

function calculateDistance(point1: Location, point2: Location): number {
    const R = 6371;
    const dLat = (point2.lat - point1.lat) * (Math.PI / 180);
    const dLon = (point2.lng - point1.lng) * (Math.PI / 180);

    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(point1.lat * (Math.PI / 180)) *
            Math.cos(point2.lat * (Math.PI / 180)) *
            Math.sin(dLon / 2) *
            Math.sin(dLon / 2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return R * c;
}

function isWithinDeliveryRadius(location: Location): boolean {
    const distance = calculateDistance(CENTRAL_LOCATION, location);

    return distance <= MAX_DELIVERY_RADIUS_KM;
}

function createDeliveryRadiusGeoJSON(): FeatureCollection {
    const points = 64;
    const km = MAX_DELIVERY_RADIUS_KM;
    const coordinates: [number, number][] = [];

    for (let i = 0; i < points; i++) {
        const angle = (i * 360) / points;
        const rad = (angle * Math.PI) / 180;
        const lat = CENTRAL_LOCATION.lat + (km / 111.32) * Math.cos(rad);
        const lng =
            CENTRAL_LOCATION.lng +
            (km / (111.32 * Math.cos((CENTRAL_LOCATION.lat * Math.PI) / 180))) *
                Math.sin(rad);
        coordinates.push([lng, lat]);
    }

    coordinates.push(coordinates[0]);

    return {
        type: 'FeatureCollection',
        features: [
            {
                type: 'Feature',
                geometry: {
                    type: 'Polygon',
                    coordinates: [coordinates],
                },
                properties: {},
            },
        ],
    };
}

export default SharedAddressForm;
