import { DishCard } from '@/components/menu/dish-card';
import { useLocale } from '@/hooks/use-locale';
import type { Category, CartSelection } from '@/lib/order-cart';

export function CategorySections({
    categories,
    onAdd,
}: {
    categories: Category[];
    onAdd: (dish: Category['dishes'][number], selection: CartSelection) => void;
}) {
    return (
        <div className="space-y-12">
            {categories.map((category) => (
                <CategorySection
                    key={category.id}
                    category={category}
                    depth={1}
                    onAdd={onAdd}
                />
            ))}
        </div>
    );
}

function CategorySection({
    category,
    depth,
    onAdd,
}: {
    category: Category;
    depth: number;
    onAdd: (dish: Category['dishes'][number], selection: CartSelection) => void;
}) {
    const { locale } = useLocale();
    const name = locale === 'ar' ? category.name_ar : category.name_en;
    const Heading = depth === 1 ? 'h2' : 'h3';

    return (
        <section id={`category-${category.id}`} className="scroll-mt-20 space-y-6">
            <Heading
                className={
                    depth === 1
                        ? 'text-2xl font-bold'
                        : 'text-xl font-semibold'
                }
            >
                {name}
            </Heading>

            {category.dishes.length > 0 ? (
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {category.dishes.map((dish) => (
                        <DishCard
                            key={dish.id}
                            dish={dish}
                            onAdd={(selection) => onAdd(dish, selection)}
                        />
                    ))}
                </div>
            ) : null}

            {category.children.length > 0 ? (
                <div className="space-y-10">
                    {category.children.map((child) => (
                        <CategorySection
                            key={child.id}
                            category={child}
                            depth={depth + 1}
                            onAdd={onAdd}
                        />
                    ))}
                </div>
            ) : null}
        </section>
    );
}

export default CategorySections;
