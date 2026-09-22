import { useAppearance } from '@/hooks/use-appearance';

export const StaticMapSimple = ({
    position,
    className = 'w-full shadow-xl aspect-square',
}: {
    position: { lng: number; lat: number };
    className?: string;
}) => {
    const { appearance } = useAppearance();
    const mapboxStyle = appearance === 'dark' ? 'dark-v11' : 'streets-v12';

    if (!position) {
        return <div className="aspect-square w-full bg-gray-100 shadow-xl" />;
    }

    const url = `https://api.mapbox.com/styles/v1/mapbox/${mapboxStyle}/static/pin-s-home+000(${position.lng},${position.lat})/${position.lng},${position.lat},15,0/600x400?access_token=${import.meta.env.VITE_MAPBOX_TOKEN}`;

    return (
        <img
            src={url}
            alt="Map"
            className={className}
            width={600}
            height={400}
        />
    );
};

export default StaticMapSimple;
