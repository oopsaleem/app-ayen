import LanguageSwitcher from '@/components/language-switcher';
import { useLocale } from '@/hooks/use-locale';

type HeroRestaurant = {
    name_en: string;
    name_ar?: string;
    description_en?: string | null;
    description_ar?: string | null;
    image_urls: string[];
};

export function Hero({ restaurant }: { restaurant: HeroRestaurant }) {
    const { locale } = useLocale();
    const name = locale === 'ar' && restaurant.name_ar ? restaurant.name_ar : restaurant.name_en;
    const description =
        locale === 'ar' ? restaurant.description_ar : restaurant.description_en;
    const image = restaurant.image_urls[0];

    return (
        <div className="relative overflow-hidden rounded-xl border">
            {image ? (
                <img
                    src={image}
                    alt=""
                    className="absolute inset-0 size-full object-cover"
                />
            ) : null}

            <div
                className={
                    image
                        ? 'relative bg-gradient-to-t from-black/70 via-black/30 to-transparent p-6 text-white'
                        : 'relative bg-muted p-6'
                }
            >
                <div className="flex items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold">{name}</h1>
                        {description ? (
                            <p
                                className={
                                    image
                                        ? 'max-w-prose text-sm text-white/90'
                                        : 'max-w-prose text-sm text-muted-foreground'
                                }
                            >
                                {description}
                            </p>
                        ) : null}
                    </div>
                    <LanguageSwitcher />
                </div>
            </div>
        </div>
    );
}

export default Hero;
